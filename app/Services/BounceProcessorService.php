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
        try {
            $client = $this->connectToImap($smtp);
            $client->connect();
        } catch (\Exception $e) {
            Log::error("BounceProcessorService: Failed to connect to IMAP for SMTP {$smtp->id}: " . $e->getMessage());
            return 0;
        }

        $bouncesProcessed = 0;
        
        try {
            $folderName = $smtp->imap_folder ?: 'INBOX';
            $folder = $client->getFolder($folderName);
            
            // Search for UNSEEN emails
            $messages = $folder->query()->unseen()->get();
            
            foreach ($messages as $message) {
                try {
                    $headerRaw = $message->getHeader()->raw ?? '';
                    $body = $message->hasHTMLBody() ? $message->getHTMLBody() : ($message->hasTextBody() ? $message->getTextBody() : '');
                    
                    $headerInfo = new \stdClass();
                    $headerInfo->from = [
                        (object) ['mailbox' => $message->getFrom()[0]->mailbox ?? '']
                    ];
                    $headerInfo->subject = (string) $message->getSubject();
                    
                    if ($this->isBounceEmail($headerInfo, $headerRaw, $body)) {
                        $bouncedEmail = $this->extractBouncedAddress($headerRaw, $body);
                        
                        if ($bouncedEmail) {
                            $this->handleBouncedAddress($smtp, $bouncedEmail);
                            $bouncesProcessed++;
                        }
                    }
                    
                    // Mark as seen so we don't process it again
                    $message->setFlag('Seen');
                    
                } catch (\Exception $e) {
                    Log::error("BounceProcessorService: Error processing email for SMTP {$smtp->id}: " . $e->getMessage());
                }
            }
            
            $client->disconnect();
        } catch (\Exception $e) {
            Log::error("BounceProcessorService: Error during mailbox operations for SMTP {$smtp->id}: " . $e->getMessage());
        }
        
        $smtp->update(['last_bounce_check_at' => now()]);
        
        if ($bouncesProcessed > 0) {
            Log::info("BounceProcessorService: Processed {$bouncesProcessed} bounces for SMTP {$smtp->id}.");
        }
        
        return $bouncesProcessed;
    }

    /**
     * Connect to the IMAP mailbox using pure PHP IMAP library.
     */
    protected function connectToImap(SmtpConfig $smtp)
    {
        $encryption = $smtp->imap_encryption === 'ssl' || $smtp->imap_encryption === 'tls' 
            ? $smtp->imap_encryption 
            : false;

        $cm = new \Webklex\PHPIMAP\ClientManager();
        
        $client = $cm->make([
            'host'          => $smtp->imap_host,
            'port'          => $smtp->imap_port ?: 993,
            'encryption'    => $encryption,
            'validate_cert' => false,
            'username'      => $smtp->username,
            'password'      => $smtp->decrypted_imap_password,
            'protocol'      => 'imap'
        ]);

        return $client;
    }

    /**
 * Determine if an email is a bounce/NDR.
 */
protected function isBounceEmail($headerInfo, string $header, string $body): bool
{
    // --- STRONG SIGNAL 1: RFC 3464 Delivery Status Notification ---
    // This is a protocol-level fact, not a guess based on wording — highest confidence.
    if (stripos($header, 'multipart/report') !== false &&
        stripos($header, 'report-type=delivery-status') !== false) {
        return true;
    }

    // Loosened variant: some servers put report-type in a MIME boundary further
    // down rather than the top header block, or format it with spaces.
    if (stripos($header, 'multipart/report') !== false &&
        preg_match('/report-type\s*=\s*delivery-status/i', $body)) {
        return true;
    }

    // --- STRONG SIGNAL 2: Sender is a known mail daemon ---
    // Real people don't send from these addresses — very reliable.
    if (isset($headerInfo->from[0])) {
        $fromAddress = strtolower($headerInfo->from[0]->mailbox ?? '');
        $daemonNames = [
            'mailer-daemon',
            'postmaster',
            'mail-daemon',
            'mailerdaemon',
            'mail delivery subsystem',
            'no-reply',
            'noreply',
        ];
        if (in_array($fromAddress, $daemonNames)) {
            return true;
        }
    }

    // --- WEAKER SIGNAL: Subject phrase match ---
    // Used as a fallback only when the two strong signals above don't fire.
    // Full phrases only — never bare words like "deliver" or "delivery",
    // since those match legitimate emails (shipping confirmations, deliverables, etc.)
    $subject = strtolower($headerInfo->subject ?? '');

    $bounceSubjectPhrases = [
        'undeliverable',
        'undelivered mail returned to sender',
        'returned mail',
        'returned to sender',
        'delivery status notification (failure)',
        'delivery status notification (delay)',
        'delivery failed',
        'delivery failure',
        'permanent delivery failure',
        'delivery incomplete',
        'message not delivered',
        'mail delivery failed',
        'mail delivery system',
        'failure notice',
        'address not found',
        'recipient address rejected',
    ];

    foreach ($bounceSubjectPhrases as $phrase) {
        if (str_contains($subject, $phrase)) {
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
        if (preg_match('/^To:\s*.*?([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/im', $body, $matches)) {
             $email = trim($matches[1]);
             if (filter_var($email, FILTER_VALIDATE_EMAIL)) return strtolower($email);
        }

        // 6. AGGRESSIVE CATCH-ALL FALLBACK
        // Especially useful for forwarded bounces (Fwd: ) where original headers are destroyed.
        // We find all emails in the body, and return the first one that exists in the DB 
        // as a 'sent' or 'failed' recipient for this user.
        if (preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $body, $matches)) {
            $foundEmails = array_unique($matches[0]);
            
            foreach ($foundEmails as $email) {
                $email = strtolower(trim($email));
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    
                    // Check if this email actually exists in the database as a recipient that was sent to
                    // This filters out the sender's own email, postmaster emails, etc.
                    $exists = \App\Models\Recipient::where('email', $email)
                        ->whereIn('status', [\App\Models\Recipient::STATUS_SENT, \App\Models\Recipient::STATUS_FAILED])
                        ->exists();
                        
                    if ($exists) {
                        return $email;
                    }
                }
            }
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


}
