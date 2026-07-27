<?php

namespace App\Services;

use App\Models\SmtpConfig;
use App\Models\Recipient;
use App\Models\InvalidEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class BounceProcessorService
{
    /**
     * Process bounces for a given SMTP config.
     */
    public function processBounces(SmtpConfig $smtp): int
    {
        if (!function_exists('imap_open')) {
            Log::error("BounceProcessorService: PHP IMAP extension not installed. Cannot process bounces for SMTP {$smtp->id}.");
            return 0;
        }

        try {
            $inbox = $this->connectToImap($smtp);
        } catch (\Exception $e) {
            Log::error("BounceProcessorService: Failed to connect to IMAP for SMTP {$smtp->id}: " . $e->getMessage());
            return 0;
        }

        $bouncesProcessed = 0;
        
        // Search for UNSEEN emails
        $emails = imap_search($inbox, 'UNSEEN');
        
        if ($emails) {
            foreach ($emails as $emailNumber) {
                try {
                    $headerInfo = imap_headerinfo($inbox, $emailNumber);
                    $structure = imap_fetchstructure($inbox, $emailNumber);
                    $header = imap_fetchheader($inbox, $emailNumber);
                    $body = $this->getBody($inbox, $emailNumber, $structure);
                    
                    if ($this->isBounceEmail($headerInfo, $header, $body)) {
                        $bouncedEmail = $this->extractBouncedAddress($header, $body);
                        
                        if ($bouncedEmail) {
                            $this->handleBouncedAddress($smtp, $bouncedEmail);
                            $bouncesProcessed++;
                        }
                    }
                    
                    // Mark as seen so we don't process it again
                    imap_setflag_full($inbox, $emailNumber, "\\Seen");
                    
                } catch (\Exception $e) {
                    Log::error("BounceProcessorService: Error processing email {$emailNumber} for SMTP {$smtp->id}: " . $e->getMessage());
                }
            }
        }
        
        imap_close($inbox);
        
        $smtp->update(['last_bounce_check_at' => now()]);
        
        if ($bouncesProcessed > 0) {
            Log::info("BounceProcessorService: Processed {$bouncesProcessed} bounces for SMTP {$smtp->id}.");
        }
        
        return $bouncesProcessed;
    }

    /**
     * Connect to the IMAP mailbox.
     */
    protected function connectToImap(SmtpConfig $smtp)
    {
        $host = $smtp->imap_host;
        $port = $smtp->imap_port ?: 993;
        $encryption = $smtp->imap_encryption === 'ssl' || $smtp->imap_encryption === 'tls' 
            ? '/' . $smtp->imap_encryption 
            : '';
        $folder = $smtp->imap_folder ?: 'INBOX';

        $mailbox = "{{$host}:{$port}/imap{$encryption}}{$folder}";
        $username = $smtp->username;
        $password = $smtp->decrypted_imap_password;

        $inbox = @imap_open($mailbox, $username, $password, 0, 1, [
            'DISABLE_AUTHENTICATOR' => 'PLAIN'
        ]);

        if (!$inbox) {
            $errors = imap_errors();
            $errorMsg = $errors ? end($errors) : 'Unknown IMAP error';
            throw new \Exception($errorMsg);
        }

        return $inbox;
    }

    /**
     * Determine if an email is a bounce/NDR.
     */
    protected function isBounceEmail($headerInfo, string $header, string $body): bool
    {
        // 1. Check if it's a Delivery Status Notification (RFC 3464)
        if (stripos($header, 'Content-Type: multipart/report') !== false && 
            stripos($header, 'report-type=delivery-status') !== false) {
            return true;
        }

        // 2. Check From address for common daemon names
        if (isset($headerInfo->from[0])) {
            $fromAddress = strtolower($headerInfo->from[0]->mailbox);
            if (in_array($fromAddress, ['mailer-daemon', 'postmaster', 'mail-daemon'])) {
                return true;
            }
        }

        // 3. Check Subject for common bounce phrases
        $subject = strtolower($headerInfo->subject ?? '');
        $bounceSubjects = [
            'undeliverable',
            'returned mail',
            'delivery status notification (failure)',
            'delivery failed',
            'failure notice',
            'mail delivery failed',
            'undelivered mail returned to sender'
        ];

        foreach ($bounceSubjects as $bounceSubj) {
            if (str_contains($subject, $bounceSubj)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract the original intended recipient from the bounce message.
     */
    protected function extractBouncedAddress(string $header, string $body): ?string
    {
        // 1. Try to find Final-Recipient in the body (standard DSN format)
        if (preg_match('/Final-Recipient:\s*rfc822;\s*([^\s]+)/i', $body, $matches)) {
            $email = trim($matches[1], "<> \t\n\r\0\x0B");
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) return strtolower($email);
        }
        
        // 2. Try Original-Recipient
        if (preg_match('/Original-Recipient:\s*rfc822;\s*([^\s]+)/i', $body, $matches)) {
            $email = trim($matches[1], "<> \t\n\r\0\x0B");
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) return strtolower($email);
        }

        // 3. Look for "Delivery to the following recipient failed permanently" patterns
        if (preg_match('/(?:delivery to|failed to deliver to).*?([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/is', $body, $matches)) {
             $email = trim($matches[1]);
             if (filter_var($email, FILTER_VALIDATE_EMAIL)) return strtolower($email);
        }
        
        // 4. Try to find X-Failed-Recipients header in the original message part
        if (preg_match('/X-Failed-Recipients:\s*([^\s]+)/i', $body, $matches)) {
            $email = trim($matches[1]);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) return strtolower($email);
        }
        
        // 5. Check To header in the attached original message (often prefixed with > or in a specific block)
        // This is a bit fragile, so we only do it if the address is explicitly marked as To:
        if (preg_match('/^To:\s*.*?([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/im', $body, $matches)) {
             // Let's make sure this To is likely from the bounced message, not the bounce wrapper
             $email = trim($matches[1]);
             if (filter_var($email, FILTER_VALIDATE_EMAIL)) return strtolower($email);
        }

        return null;
    }

    /**
     * Handle the discovered bounced address.
     */
    protected function handleBouncedAddress(SmtpConfig $smtp, string $email): void
    {
        DB::transaction(function () use ($smtp, $email) {
            // 1. Find all recipients for this user's campaigns that were sent to this email
            // and are currently in a 'sent' or 'failed' state (maybe temporary fail).
            // We update them to 'bounced'.
            
            // Note: A user might send to the same email in multiple campaigns.
            // If it bounced now, it's generally safe to mark it bounced in active/recent campaigns.
            
            $updated = Recipient::whereHas('campaign', function ($q) use ($smtp) {
                    $q->where('user_id', $smtp->user_id);
                })
                ->where('email', $email)
                ->whereIn('status', [Recipient::STATUS_SENT, Recipient::STATUS_FAILED])
                ->update([
                    'status' => Recipient::STATUS_BOUNCED,
                    'error_message' => 'IMAP Bounce Detected: Delivery failed.'
                ]);

            if ($updated > 0) {
                // Add to invalid emails blocklist so it's not mailed again
                InvalidEmail::addEmail($smtp->user_id, $email, 'Hard Bounce via IMAP');
                
                // Record the bounce on the SMTP config for tracking limits/pausing
                // We only count it once even if it updated multiple recipient records
                $smtp->recordBounce();
                
                Log::info("BounceProcessorService: Marked {$email} as bounced for user {$smtp->user_id} (updated {$updated} recipient records).");
            }
        });
    }

    /**
     * Recursively fetch email body from IMAP structure.
     */
    protected function getBody($inbox, int $emailNumber, $structure, $partNumber = ""): string
    {
        $body = "";
        
        if (empty($partNumber)) {
            $partNumber = "1";
        }

        // If no parts, it's a simple message
        if (!isset($structure->parts) || empty($structure->parts)) {
            // Get the whole body
            $text = imap_fetchbody($inbox, $emailNumber, $partNumber);
            $body .= $this->decodeBody($text, $structure->encoding ?? 0);
            return $body;
        }

        // Multipart message
        foreach ($structure->parts as $index => $subStruct) {
            $prefix = $partNumber ? $partNumber . '.' : '';
            $subPartNumber = $prefix . ($index + 1);

            // We want text parts (0) or message/rfc822 parts (2) which contain the original headers
            if ($subStruct->type == 0 || $subStruct->type == 2) {
                $text = imap_fetchbody($inbox, $emailNumber, $subPartNumber);
                $body .= $this->decodeBody($text, $subStruct->encoding ?? 0) . "\n\n";
            }
            
            // Recursively get nested parts
            if (isset($subStruct->parts)) {
                 $body .= $this->getBody($inbox, $emailNumber, $subStruct, $subPartNumber);
            }
        }
        
        return $body;
    }

    /**
     * Decode IMAP email body based on encoding.
     */
    protected function decodeBody(string $text, int $encoding): string
    {
        switch ($encoding) {
            case 3: // BASE64
                return imap_base64($text);
            case 4: // QUOTED-PRINTABLE
                return imap_qprint($text);
            default:
                return $text;
        }
    }
}
