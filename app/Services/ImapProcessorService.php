<?php

namespace App\Services;

use App\Models\SmtpConfig;
use App\Models\Recipient;
use App\Models\InvalidEmail;
use App\Models\Reply;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ImapProcessorService
{
    /**
     * Process bounces and replies for a given SMTP config.
     *
     * @param SmtpConfig $smtp
     * @param int $maxMessages Maximum messages to process per check to prevent memory/timeout exhaustion
     * @param bool $throwOnError Whether to rethrow connection/folder errors (e.g. for manual UI sync feedback)
     */
    public function processInbox(SmtpConfig $smtp, int $maxMessages = 25, bool $throwOnError = false): int
    {
        // Always update last_bounce_check_at first so a failing/unreachable IMAP server rotates to the back of the queue
        $smtp->update(['last_bounce_check_at' => now()]);

        try {
            $client = $this->connectToImap($smtp);
            $client->connect();
        } catch (\Exception $e) {
            Log::error("ImapProcessorService: Failed to connect to IMAP for SMTP {$smtp->id}: " . $e->getMessage());
            if ($throwOnError) {
                throw $e;
            }
            return 0;
        }

        $emailsProcessed = 0;
        
        try {
            $foldersToScan = $this->resolveFoldersToScan($client, $smtp, $throwOnError);
            if (empty($foldersToScan)) {
                $client->disconnect();
                return 0;
            }
            
            // Load processed message UIDs for this SMTP so we can safely inspect recent messages (even if already marked Seen in webmail/phone)
            $processedUidsKey = "imap_processed_uids:{$smtp->id}";
            $processedUids = \Illuminate\Support\Facades\Cache::get($processedUidsKey, []);
            $processedUidsMap = array_flip($processedUids);
            $uidsModified = false;

            $smtpOwnEmails = array_filter([
                strtolower(trim($smtp->from_email ?? '')),
                strtolower(trim($smtp->username ?? '')),
            ]);

            foreach ($foldersToScan as $folder) {
                $folderKey = strtolower($folder->path ?? $folder->name ?? 'inbox');

                // Search recent emails from last 3 days (both Seen & Unseen so webmail-opened or spam-filtered replies/bounces are never missed)
                try {
                    $query = $folder->query()->since(now()->subDays(3));
                    if (method_exists($query, 'setFetchAttachment')) {
                        $query->setFetchAttachment(false);
                    }
                    if (method_exists($query, 'limit')) {
                        $query->limit($maxMessages);
                    }
                    $messages = $query->get();
                } catch (\Throwable $queryErr) {
                    try {
                        $query = $folder->query()->unseen();
                        if (method_exists($query, 'setFetchAttachment')) {
                            $query->setFetchAttachment(false);
                        }
                        if (method_exists($query, 'limit')) {
                            $query->limit($maxMessages);
                        }
                        $messages = $query->get();
                    } catch (\Throwable $fallbackErr) {
                        continue;
                    }
                }

                foreach ($messages as $message) {
                    try {
                        $rawUid = method_exists($message, 'getUid') ? (string) $message->getUid() : null;
                        $scopedUid = $rawUid ? "{$folderKey}:{$rawUid}" : null;

                        // Check both folder-scoped UID and legacy unscoped UID
                        if ($scopedUid && (isset($processedUidsMap[$scopedUid]) || ($folderKey === 'inbox' && isset($processedUidsMap[$rawUid])))) {
                            continue;
                        }

                        $headerRaw = $message->getHeader()->raw ?? '';
                        $htmlBody = $message->hasHTMLBody() ? $message->getHTMLBody() : null;
                        $textBody = $message->hasTextBody() ? $message->getTextBody() : ($htmlBody ? trim(strip_tags($htmlBody)) : '');
                        $combinedBody = ($htmlBody ?: '') . "\n" . ($textBody ?: '');
                        
                        // Safely extract From address
                        $fromObj = null;
                        try {
                            $fromCollection = $message->getFrom();
                            if ($fromCollection && method_exists($fromCollection, 'first')) {
                                $fromObj = $fromCollection->first();
                            } elseif (is_array($fromCollection) && !empty($fromCollection)) {
                                $fromObj = $fromCollection[0];
                            }
                        } catch (\Throwable $e) {
                            // Ignore extraction exception
                        }

                        $fromMailbox = $fromObj ? ($fromObj->mailbox ?? '') : '';
                        $fromFullEmail = '';

                        if ($fromObj) {
                            if (!empty($fromObj->mail)) {
                                $fromFullEmail = $fromObj->mail;
                            } elseif (!empty($fromObj->mailbox) && !empty($fromObj->host)) {
                                $fromFullEmail = $fromObj->mailbox . '@' . $fromObj->host;
                            }
                        }

                        // Fallback to Reply-To if From was empty
                        if (empty($fromFullEmail)) {
                            try {
                                $replyToCollection = $message->getReplyTo();
                                $replyToObj = ($replyToCollection && method_exists($replyToCollection, 'first')) 
                                    ? $replyToCollection->first() 
                                    : (is_array($replyToCollection) && !empty($replyToCollection) ? $replyToCollection[0] : null);

                                if ($replyToObj) {
                                    if (!empty($replyToObj->mail)) {
                                        $fromFullEmail = $replyToObj->mail;
                                    } elseif (!empty($replyToObj->mailbox) && !empty($replyToObj->host)) {
                                        $fromFullEmail = $replyToObj->mailbox . '@' . $replyToObj->host;
                                    }
                                }
                            } catch (\Throwable $e) {
                                // Ignore fallback exception
                            }
                        }

                        $fromFullEmail = trim(strtolower($fromFullEmail), "<>\"' \t\n\r\0\x0B");

                        $headerInfo = new \stdClass();
                        $headerInfo->from = [
                            (object) ['mailbox' => $fromMailbox]
                        ];
                        $headerInfo->subject = (string) $message->getSubject();
                        
                        if ($this->isBounceEmail($headerInfo, $headerRaw, $combinedBody)) {
                            $bouncedEmail = $this->extractBouncedAddress($headerRaw, $combinedBody, $smtp);
                            
                            if ($bouncedEmail) {
                                $this->handleBouncedAddress($smtp, $bouncedEmail);
                                $emailsProcessed++;
                            }
                        } else {
                            // Ignore self-test emails sent from the SMTP account itself
                            if (!in_array($fromFullEmail, $smtpOwnEmails, true) && filter_var($fromFullEmail, FILTER_VALIDATE_EMAIL)) {
                                $wasReply = $this->handleReplyAddress($smtp, $fromFullEmail, $headerInfo->subject, $textBody, $htmlBody);
                                if ($wasReply) {
                                    $emailsProcessed++;
                                }
                            }
                        }
                        
                        // Mark as seen and record folder-scoped UID in cache so we never re-process this message
                        if ($scopedUid) {
                            $processedUidsMap[$scopedUid] = true;
                            $uidsModified = true;
                        }
                        try {
                            $message->setFlag('Seen');
                        } catch (\Throwable $e) {
                            // Non-fatal if folder is read-only; UID cache prevents re-processing
                        }
                        
                    } catch (\Exception $e) {
                        Log::error("ImapProcessorService: Error processing email for SMTP {$smtp->id} in folder {$folderKey}: " . $e->getMessage());
                    }
                }
            }

            if ($uidsModified) {
                // Keep most recent 600 UIDs per SMTP for 7 days
                $updatedUids = array_slice(array_keys($processedUidsMap), -600);
                \Illuminate\Support\Facades\Cache::put($processedUidsKey, $updatedUids, 86400 * 7);
            }
            
            $client->disconnect();
        } catch (\Exception $e) {
            Log::error("ImapProcessorService: Error during mailbox operations for SMTP {$smtp->id}: " . $e->getMessage());
            if ($throwOnError) {
                throw $e;
            }
        }
        
        if ($emailsProcessed > 0) {
            Log::info("ImapProcessorService: Processed {$emailsProcessed} emails for SMTP {$smtp->id}.");
        }
        
        return $emailsProcessed;
    }

    /**
     * Resolve both the primary Inbox folder(s) AND any Spam/Junk folders on the IMAP server
     * so replies and bounces landing in Spam/Junk are automatically captured too.
     */
    protected function resolveFoldersToScan($client, SmtpConfig $smtp, bool $throwOnError = false): array
    {
        $configuredRaw = $smtp->imap_folder ?: 'INBOX';
        $requestedNames = array_filter(array_map(fn($f) => strtolower(trim($f)), explode(',', $configuredRaw)));
        if (empty($requestedNames)) {
            $requestedNames = ['inbox'];
        }

        // Standard Spam/Junk folder names across cPanel/Dovecot, Gmail, Outlook/M365, Zoho, Hostinger, Yahoo
        $spamFolderNames = [
            'spam',
            'junk',
            'junk e-mail',
            'junk email',
            'bulk',
            'bulk mail',
            'inbox.spam',
            'inbox.junk',
            '[gmail]/spam',
            '[google mail]/spam',
        ];

        $matchedFolders = [];
        $availablePaths = [];

        try {
            // Fetch folder tree (hierarchical = true so [Gmail]/Spam and INBOX.spam children are included)
            $allFolders = $client->getFolders(true);
            $flatFolders = [];

            $flatten = function ($folders) use (&$flatten, &$flatFolders) {
                foreach ($folders as $f) {
                    $flatFolders[] = $f;
                    if (!empty($f->children) && is_iterable($f->children)) {
                        $flatten($f->children);
                    }
                }
            };
            $flatten($allFolders);

            foreach ($flatFolders as $f) {
                $nameLower = strtolower($f->name ?? '');
                $pathLower = strtolower($f->path ?? '');
                $availablePaths[] = $f->path ?? $f->name;

                $isRequested = in_array($nameLower, $requestedNames, true) || in_array($pathLower, $requestedNames, true);
                $isSpamOrJunk = in_array($nameLower, $spamFolderNames, true)
                    || in_array($pathLower, $spamFolderNames, true)
                    || str_ends_with($pathLower, '.spam')
                    || str_ends_with($pathLower, '.junk')
                    || str_ends_with($pathLower, '/spam')
                    || str_ends_with($pathLower, '/junk');

                if ($isRequested || $isSpamOrJunk) {
                    $matchedFolders[$pathLower ?: $nameLower] = $f;
                }
            }
        } catch (\Throwable $e) {
            // Fallback to direct getFolder() if listing all folders is not supported
        }

        // Fallback if folder tree listing didn't return the primary folder
        if (empty($matchedFolders)) {
            $primary = $client->getFolder($smtp->imap_folder ?: 'INBOX');
            if ($primary) {
                $matchedFolders[strtolower($primary->path ?? 'inbox')] = $primary;
            }
            // Also try direct lookup of common Spam/Junk folder names
            foreach (['Spam', 'Junk', 'INBOX.spam', 'INBOX.Junk', '[Gmail]/Spam'] as $spamName) {
                try {
                    $sf = $client->getFolder($spamName);
                    if ($sf) {
                        $matchedFolders[strtolower($sf->path ?? $spamName)] = $sf;
                        break;
                    }
                } catch (\Throwable $e) {
                    // Ignore missing spam folder
                }
            }
        }

        if (empty($matchedFolders)) {
            $foldersStr = !empty($availablePaths) ? implode(', ', $availablePaths) : 'None';
            $errMsg = "Folder '{$configuredRaw}' not found for SMTP {$smtp->id}. Available folders: {$foldersStr}";
            Log::error("ImapProcessorService: {$errMsg}");
            if ($throwOnError) {
                throw new \RuntimeException($errMsg);
            }
        }

        return array_values($matchedFolders);
    }

    /**
     * Connect to the IMAP mailbox using pure PHP IMAP library with strict shared-hosting timeout.
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
            'protocol'      => 'imap',
            'timeout'       => 8,
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
        if (in_array($fromAddress, $daemonNames, true) || str_contains($fromAddress, 'mailer-daemon') || str_contains($fromAddress, 'postmaster')) {
            return true;
        }
    }

    // --- STRONG SIGNAL 3: Standard RFC 3464 / 3463 DSN body markers ---
    if (preg_match('/(?:Final-Recipient:\s*rfc822;|Diagnostic-Code:\s*smtp;\s*5\d\d|Action:\s*failed|Status:\s*5\.\d+\.\d+)/i', $body)) {
        return true;
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
    protected function extractBouncedAddress(string $header, string $body, ?SmtpConfig $smtp = null): ?string
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

        // 6. CATCH-ALL FALLBACK using a single WHERE IN query instead of N+1 queries
        if (preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $body, $matches)) {
            $excluded = [];
            if ($smtp) {
                if (!empty($smtp->from_email)) $excluded[strtolower($smtp->from_email)] = true;
                if (!empty($smtp->username)) $excluded[strtolower($smtp->username)] = true;
            }

            $candidates = [];
            foreach (array_unique($matches[0]) as $rawEmail) {
                $candidate = strtolower(trim($rawEmail));
                if (!isset($excluded[$candidate]) && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                    $candidates[] = $candidate;
                }
            }

            if (!empty($candidates)) {
                $matched = \App\Models\Recipient::whereIn('email', array_slice($candidates, 0, 50))
                    ->whereIn('status', [\App\Models\Recipient::STATUS_SENT, \App\Models\Recipient::STATUS_FAILED])
                    ->value('email');

                if ($matched) {
                    return strtolower($matched);
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
        $email = strtolower(trim($email));

        DB::transaction(function () use ($smtp, $email) {
            $bouncedRecipientIds = Recipient::whereIn(
                    'campaign_id',
                    \App\Models\Campaign::where('user_id', $smtp->user_id)->select('id')
                )
                ->where('email', $email)
                ->whereIn('status', [Recipient::STATUS_SENT, Recipient::STATUS_FAILED])
                ->pluck('id')
                ->all();

            $updated = 0;
            if (!empty($bouncedRecipientIds)) {
                $updated = Recipient::whereIn('id', $bouncedRecipientIds)
                    ->update([
                        'status' => Recipient::STATUS_BOUNCED,
                        'error_message' => 'IMAP Bounce Detected: Delivery failed.',
                        'smtp_config_id' => DB::raw('COALESCE(smtp_config_id, ' . (int) $smtp->id . ')'),
                    ]);

                \App\Models\EmailLog::whereIn('recipient_id', $bouncedRecipientIds)
                    ->update([
                        'status' => \App\Models\EmailLog::STATUS_BOUNCED,
                        'error_message' => 'IMAP Bounce Detected: Delivery failed.',
                    ]);
            }

            if ($updated > 0) {
                InvalidEmail::addEmail($smtp->user_id, $email, 'Hard Bounce via IMAP');

                \App\Models\Contact::whereIn(
                        'contact_list_id',
                        \App\Models\ContactList::where('user_id', $smtp->user_id)->select('id')
                    )
                    ->where('email', $email)
                    ->update([
                        'validation_status' => \App\Models\Contact::STATUS_INVALID,
                        'validation_error' => 'Hard Bounce via IMAP',
                        'validated_at' => now(),
                    ]);

                $smtp->recordBounce(true);

                Log::info("ImapProcessorService: Marked {$email} as bounced for user {$smtp->user_id} (updated {$updated} recipient records).");
            }
        });
    }

    /**
     * Handle the discovered reply address.
     */
    protected function handleReplyAddress(SmtpConfig $smtp, string $email, string $subject, string $bodyText, ?string $bodyHtml): bool
    {
        $wasReply = false;
        $email = strtolower(trim($email));
        
        DB::transaction(function () use ($smtp, $email, $subject, $bodyText, $bodyHtml, &$wasReply) {
            // Find the most recent recipient in any campaign for this user using indexed lowercase email lookup
            $recipient = Recipient::whereIn(
                    'campaign_id',
                    \App\Models\Campaign::where('user_id', $smtp->user_id)->select('id')
                )
                ->where('email', $email)
                ->orderByRaw("CASE WHEN status IN ('sent', 'replied') THEN 0 ELSE 1 END")
                ->orderBy('sent_at', 'desc')
                ->orderBy('updated_at', 'desc')
                ->first();

            if ($recipient) {
                // Prevent duplicate reply records within the last 7 days
                $alreadyExists = Reply::where('user_id', $smtp->user_id)
                    ->where('recipient_id', $recipient->id)
                    ->where('from_email', $email)
                    ->where('subject', $subject)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->exists();

                if ($alreadyExists) {
                    Log::info("ImapProcessorService: Reply already recorded from {$email} for recipient {$recipient->id}.");
                    return;
                }

                $wasReply = true;
                
                // If this is the FIRST reply, update the recipient status
                if ($recipient->status !== Recipient::STATUS_REPLIED) {
                    $recipient->update([
                        'status' => Recipient::STATUS_REPLIED,
                        'replied_at' => now(),
                    ]);
                }

                // Store the actual reply in the database for the Unified Inbox
                Reply::create([
                    'user_id' => $smtp->user_id,
                    'campaign_id' => $recipient->campaign_id,
                    'recipient_id' => $recipient->id,
                    'smtp_config_id' => $smtp->id,
                    'from_email' => $email,
                    'subject' => $subject,
                    'body_text' => $bodyText,
                    'body_html' => $bodyHtml,
                    'received_at' => now(),
                ]);
                
                // Increment total replies on the SMTP config
                $smtp->increment('total_replies');
                
                Log::info("ImapProcessorService: Stored reply from {$email} for user {$smtp->user_id} (Campaign {$recipient->campaign_id}, Recipient {$recipient->id}).");
            } else {
                Log::info("ImapProcessorService: Ignored email from {$email} - no matching campaign recipient found for user {$smtp->user_id}.");
            }
        });
        
        return $wasReply;
    }

}
