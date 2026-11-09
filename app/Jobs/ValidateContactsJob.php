<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\ContactList;
use App\Services\EmailValidatorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Log;

/**
 * This job ONLY performs SMTP mailbox verification.
 * All other quick checks (syntax, typo, disposable, DNS, etc.) 
 * are now done inline during contact import.
 */
class ValidateContactsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $batchSize = 50; // Larger batch size since DB writes and DNS lookups are now cached/batched
    protected int $maxExecutionTime = 20; // Tight burst to prevent cron overlap and CPU starvation
    protected float $startTime;
    public int $uniqueFor = 300; // 5 minute timeout for the unique lock (prevents stuck locks on shared hosting)


    /**
     * Create a new job instance.
     */
    public function __construct(public ContactList $contactList, int $maxExecutionTime = 20)
    {
        $this->maxExecutionTime = $maxExecutionTime;
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return (string) $this->contactList->id;
    }

    /**
     * Execute the job - DNS/MX and SMTP mailbox verification.
     */
    public function handle(EmailValidatorService $validator): void
    {
        $this->startTime = microtime(true);

        // Recover any contacts stuck in 'validating' status from an interrupted shared-hosting process (> 5 mins ago)
        $this->contactList->contacts()
            ->where('validation_status', Contact::STATUS_VALIDATING)
            ->where('updated_at', '<', now()->subMinutes(5))
            ->update(['validation_status' => Contact::STATUS_PENDING]);

        // Early exit: if no pending contacts, skip immediately and clear has_pending_contacts flag
        $hasPending = $this->contactList->contacts()->pendingValidation()->exists();
        if (!$hasPending) {
            if ($this->contactList->has_pending_contacts) {
                $this->contactList->update(['has_pending_contacts' => false]);
            }
            return;
        }

        $validated = 0;
        $valid = 0;
        $invalid = 0;
        $skipped = 0;

        // Process pending contacts in batches
        while (true) {
            if ($this->isTimeExceeded()) {
                break;
            }

            $contacts = $this->contactList->contacts()
                ->pendingValidation()
                ->orderBy('id', 'asc')
                ->limit($this->batchSize)
                ->get();

            if ($contacts->isEmpty()) {
                break;
            }

            // Mark batch as validating in 1 query
            $contactIds = $contacts->pluck('id')->all();
            Contact::whereIn('id', $contactIds)->update(['validation_status' => Contact::STATUS_VALIDATING]);

            // Pre-fetch cached results and blocklist for this batch in 2 queries
            $emails = $contacts->pluck('email')->map(fn($e) => strtolower(trim($e)))->unique()->values()->all();

            $cachedEmails = \App\Models\GlobalEmailCache::whereIn('email', $emails)->get()->keyBy('email');

            $blocklistedEmails = \App\Models\InvalidEmail::where('user_id', $this->contactList->user_id)
                ->whereIn('email', $emails)
                ->get()
                ->keyBy('email');

            $now = now();
            $nowString = $now->toDateTimeString();
            $cacheRowsToUpsert = [];
            $invalidEmailsToInsert = [];
            $validCampaignSyncEmails = [];
            $invalidCampaignSyncEmails = [];

            foreach ($contacts as $key => $contact) {
                // Check if we're running out of time during a slow batch
                if ($this->isTimeExceeded()) {
                    $remainingIds = $contacts->slice($key)->pluck('id')->all();
                    if (!empty($remainingIds)) {
                        Contact::whereIn('id', $remainingIds)->update(['validation_status' => Contact::STATUS_PENDING]);
                    }
                    $this->flushBatchSyncs(
                        $cacheRowsToUpsert,
                        $invalidEmailsToInsert,
                        $validCampaignSyncEmails,
                        $invalidCampaignSyncEmails,
                        $nowString
                    );
                    break 2;
                }

                $email = strtolower(trim($contact->email));
                $fromCache = false;

                // 1. Check if email is in the user's personal blocklist
                if ($blocklistedEmails->has($email)) {
                    $mailboxResult = [
                        'status' => 'invalid',
                        'reason' => 'Blocklisted: ' . ($blocklistedEmails->get($email)->reason ?? 'User blocklist'),
                    ];
                } else {
                    // 2. Lightning Fast Local Pre-check (Syntax & Typo & Role-based & Disposable)
                    $precheck = $validator->quickPrecheck($email);

                    if ($precheck !== false) {
                        $mailboxResult = [
                            'status' => $precheck['status'],
                            'reason' => $precheck['reason'],
                        ];
                    } elseif ($cachedEmails->has($email)) {
                        // 3. Check Global Database Cache
                        $cached = $cachedEmails->get($email);
                        $mailboxResult = [
                            'status' => $cached->status,
                            'reason' => $cached->reason,
                        ];
                        $fromCache = true;
                    } else {
                        // 4. Verify via API or cached DNS/MX fallback
                        $mailboxResult = $validator->verifyWithThirdPartyApi($email);
                    }
                }

                // Queue for bulk cache upsert if not already read from cache
                if (!$fromCache && in_array($mailboxResult['status'], ['valid', 'invalid', 'risky', 'unknown'], true)) {
                    $cacheRowsToUpsert[$email] = [
                        'email' => $email,
                        'status' => $mailboxResult['status'],
                        'reason' => $mailboxResult['reason'] ?? null,
                        'created_at' => $nowString,
                        'updated_at' => $nowString,
                    ];
                }

                $validated++;

                if ($mailboxResult['status'] === 'valid') {
                    $contact->markAsValid([
                        'verification_method' => 'third_party_api',
                        'note' => $mailboxResult['reason'] ?? null,
                    ]);
                    $validCampaignSyncEmails[] = $email;
                    $valid++;
                } elseif ($mailboxResult['status'] === 'invalid') {
                    $reason = $mailboxResult['reason'] ?? 'Invalid email';
                    $contact->markAsInvalid([
                        'verification_method' => 'third_party_api',
                    ], $reason);
                    $invalidEmailsToInsert[$email] = [
                        'user_id' => $this->contactList->user_id,
                        'email' => $email,
                        'reason' => $reason,
                        'detected_at' => $nowString,
                        'created_at' => $nowString,
                        'updated_at' => $nowString,
                    ];
                    $invalidCampaignSyncEmails[] = $email;
                    $invalid++;
                } elseif (in_array($mailboxResult['status'], ['risky', 'unknown'], true)) {
                    $contact->markAsValid([
                        'verification_method' => 'third_party_api',
                        'risky' => true,
                        'api_status' => $mailboxResult['status'],
                        'note' => $mailboxResult['reason'] ?? 'Risky / Unknown',
                    ]);
                    $validCampaignSyncEmails[] = $email;
                    $valid++;
                } else {
                    $attempts = ($contact->validation_result['attempts'] ?? 0) + 1;

                    if ($attempts >= 3) {
                        $contact->markAsValid([
                            'verification_method' => 'third_party_api',
                            'risky' => true,
                            'api_status' => 'unknown',
                            'note' => 'Max connection retries exceeded (' . ($mailboxResult['reason'] ?? 'Connection failed') . ')',
                        ]);
                        $validCampaignSyncEmails[] = $email;
                        $valid++;
                    } else {
                        $contact->update([
                            'validation_status' => Contact::STATUS_PENDING,
                            'validation_result' => [
                                'attempts' => $attempts,
                                'reason' => $mailboxResult['reason'] ?? 'API connection failed, will retry',
                            ],
                            'validation_error' => 'Temporarily skipped (Attempt ' . $attempts . ')',
                        ]);
                        $skipped++;
                    }
                }
            }

            // Flush batch writes (GlobalEmailCache, InvalidEmail blocklist, and Campaign Recipients sync)
            $this->flushBatchSyncs(
                $cacheRowsToUpsert,
                $invalidEmailsToInsert,
                $validCampaignSyncEmails,
                $invalidCampaignSyncEmails,
                $nowString
            );
        }

        // Update has_pending_contacts flag using fast exists() check
        $stillHasPending = $this->contactList->contacts()->pendingValidation()->exists();
        if ($this->contactList->has_pending_contacts !== $stillHasPending) {
            $this->contactList->update(['has_pending_contacts' => $stillHasPending]);
        }

        if ($validated > 0) {
            Log::info("ValidateContactsJob: List {$this->contactList->id} - Verified: {$validated}, Valid: {$valid}, Invalid: {$invalid}, Skipped: {$skipped}, HasMorePending: " . ($stillHasPending ? 'yes' : 'no'));
        }
    }

    /**
     * Flush batched cache upserts, blocklist inserts, and campaign recipient syncs in minimal SQL queries.
     */
    protected function flushBatchSyncs(
        array $cacheRowsToUpsert,
        array $invalidEmailsToInsert,
        array $validCampaignSyncEmails,
        array $invalidCampaignSyncEmails,
        string $nowString
    ): void {
        if (!empty($cacheRowsToUpsert)) {
            \App\Models\GlobalEmailCache::upsert(
                array_values($cacheRowsToUpsert),
                ['email'],
                ['status', 'reason', 'updated_at']
            );
        }

        if (!empty($invalidEmailsToInsert)) {
            \Illuminate\Support\Facades\DB::table('invalid_emails')->insertOrIgnore(array_values($invalidEmailsToInsert));
        }

        if (!empty($validCampaignSyncEmails)) {
            \App\Models\Recipient::whereIn('email', array_unique($validCampaignSyncEmails))
                ->whereIn('status', [\App\Models\Recipient::STATUS_PENDING, \App\Models\Recipient::STATUS_VALIDATING])
                ->update([
                    'status' => \App\Models\Recipient::STATUS_VALID,
                    'validated_at' => $nowString,
                    'updated_at' => $nowString,
                ]);
        }

        if (!empty($invalidCampaignSyncEmails)) {
            \App\Models\Recipient::whereIn('email', array_unique($invalidCampaignSyncEmails))
                ->whereIn('status', [\App\Models\Recipient::STATUS_PENDING, \App\Models\Recipient::STATUS_VALIDATING])
                ->delete();
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ValidateContactsJob: Failed for list {$this->contactList->id}: {$exception->getMessage()}");
    }

    /**
     * Check if the job has exceeded its time limit.
     */
    protected function isTimeExceeded(): bool
    {
        return (microtime(true) - $this->startTime) > $this->maxExecutionTime;
    }

    /**
     * Synchronize the validation result to any active campaigns that are waiting on this email.
     */
    protected function syncValidationToActiveCampaigns(string $email, string $status, array $result = [], ?string $reason = null): void
    {
        if ($status === 'valid' || in_array($status, ['risky', 'unknown'])) {
            \App\Models\Recipient::where('email', $email)
                ->whereIn('status', [\App\Models\Recipient::STATUS_PENDING, \App\Models\Recipient::STATUS_VALIDATING])
                ->update([
                    'status' => \App\Models\Recipient::STATUS_VALID,
                    'validation_result' => json_encode(array_merge($result, [
                        'api_status' => $status,
                        'note' => $reason
                    ])),
                    'validated_at' => now(),
                ]);
        } else {
            // invalid, disposable, blocklisted, etc.
            $pendingRecipients = \App\Models\Recipient::with('campaign')
                ->where('email', $email)
                ->whereIn('status', [\App\Models\Recipient::STATUS_PENDING, \App\Models\Recipient::STATUS_VALIDATING])
                ->get();
                
            foreach ($pendingRecipients as $recipient) {
                // Ensure campaign exists before calling markAsInvalid which depends on it
                if ($recipient->campaign) {
                    if ($status === 'disposable') {
                        $recipient->markAsDisposable($result);
                    } else {
                        $recipient->markAsInvalid($result, $reason ?? 'Invalid email');
                    }
                } else {
                    $recipient->delete();
                }
            }
        }
    }
}

