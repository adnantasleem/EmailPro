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
use Illuminate\Support\Facades\Log;

/**
 * This job ONLY performs SMTP mailbox verification.
 * All other quick checks (syntax, typo, disposable, DNS, etc.) 
 * are now done inline during contact import.
 */
class ValidateContactsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $batchSize = 20; // Smaller batch for slow SMTP checks
    protected int $maxExecutionTime = 50; // Max seconds to run (cron is 55s, leave buffer)
    protected float $startTime;

    /**
     * Create a new job instance.
     */
    public function __construct(public ContactList $contactList)
    {
        //
    }

    /**
     * Execute the job - DNS/MX and SMTP mailbox verification.
     */
    public function handle(EmailValidatorService $validator): void
    {
        $this->startTime = microtime(true);
        
        Log::info("ValidateContactsJob: Server-side validation for list {$this->contactList->id} - {$this->contactList->name}");

        $validated = 0;
        $valid = 0;
        $invalid = 0;
        $skipped = 0;

        // Process pending contacts in batches
        while (true) {
            // Check if we're running out of time
            if ($this->isTimeExceeded()) {
                Log::info("ValidateContactsJob: Time limit reached for list {$this->contactList->id}. Processed {$validated} contacts. Will continue next run.");
                break;
            }

            $contacts = $this->contactList->contacts()
                ->pendingValidation()
                ->limit($this->batchSize)
                ->get();

            if ($contacts->isEmpty()) {
                break;
            }

            // Mark as validating
            $contactIds = $contacts->pluck('id')->toArray();
            Contact::whereIn('id', $contactIds)->update(['validation_status' => Contact::STATUS_VALIDATING]);

            // Pre-fetch cached results and blocklist for this batch
            $emails = $contacts->pluck('email')->map(fn($e) => strtolower($e))->toArray();
            
            $cachedEmails = \App\Models\GlobalEmailCache::whereIn('email', $emails)->get()->keyBy('email');
            
            $blocklistedEmails = \App\Models\InvalidEmail::where('user_id', $this->contactList->user_id)
                ->whereIn('email', $emails)
                ->get()
                ->keyBy('email');

            foreach ($contacts as $key => $contact) {
                // Check if we're running out of time during a slow batch
                if ($this->isTimeExceeded()) {
                    $remainingIds = $contacts->slice($key)->pluck('id')->toArray();
                    if (!empty($remainingIds)) {
                        Contact::whereIn('id', $remainingIds)->update(['validation_status' => Contact::STATUS_PENDING]);
                    }
                    Log::info("ValidateContactsJob: Time limit reached inside batch for list {$this->contactList->id}. Reverted " . count($remainingIds) . " unprocessed contacts to pending.");
                    break 2; // Break out of foreach and while loop
                }

                $contact->refresh();
                $email = strtolower($contact->email);

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
                        // It failed the quick local precheck
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
                    } else {
                        // 4. Call the Third-Party API directly
                        $mailboxResult = $validator->verifyWithThirdPartyApi($email);
                    }
                }
                    
                    // Save to cache if successful
                    if (in_array($mailboxResult['status'], ['valid', 'invalid', 'risky', 'unknown'])) {
                        \App\Models\GlobalEmailCache::create([
                            'email' => $email,
                            'status' => $mailboxResult['status'],
                            'reason' => $mailboxResult['reason'] ?? null,
                        ]);
                    }
                
                $validated++;

                if ($mailboxResult['status'] === 'valid') {
                    // Mailbox verified
                    $contact->markAsValid([
                        'verification_method' => 'third_party_api',
                        'note' => $mailboxResult['reason'] ?? null,
                    ]);
                    $valid++;
                } elseif ($mailboxResult['status'] === 'invalid') {
                    // Mailbox doesn't exist or is invalid
                    $contact->markAsInvalid([
                        'verification_method' => 'third_party_api',
                    ], $mailboxResult['reason'] ?? 'Invalid email');
                    $invalid++;
                } elseif (in_array($mailboxResult['status'], ['risky', 'unknown'])) {
                    // Risky or unknown emails shouldn't be retried indefinitely
                    $contact->markAsValid([
                        'verification_method' => 'third_party_api',
                        'risky' => true,
                        'api_status' => $mailboxResult['status'],
                        'note' => $mailboxResult['reason'] ?? 'Risky / Unknown',
                    ]);
                    $valid++;
                } else {
                    // Error (like connection failed) - Mark back as pending so it retries later
                    $contact->update([
                        'validation_status' => Contact::STATUS_PENDING,
                        'validation_result' => [
                            'reason' => $mailboxResult['reason'] ?? 'API connection failed, will retry',
                        ],
                        'validation_error' => 'Temporarily skipped',
                    ]);
                    $skipped++;
                }
            }
        }

        Log::info("ValidateContactsJob: List {$this->contactList->id} - Verified: {$validated}, Valid: {$valid}, Invalid: {$invalid}, Skipped: {$skipped}");
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
}

