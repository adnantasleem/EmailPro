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

            // Pre-fetch cached results for this batch
            $emails = $contacts->pluck('email')->map(fn($e) => strtolower($e))->toArray();
            $cachedEmails = \App\Models\GlobalEmailCache::whereIn('email', $emails)->get()->keyBy('email');

            foreach ($contacts as $contact) {
                $contact->refresh();
                $email = strtolower($contact->email);

                if ($cachedEmails->has($email)) {
                    $cached = $cachedEmails->get($email);
                    $mailboxResult = [
                        'status' => $cached->status,
                        'reason' => $cached->reason,
                    ];
                } else {
                    // Call the Third-Party API directly
                    $mailboxResult = $validator->verifyWithThirdPartyApi($email);
                    
                    // Save to cache if successful
                    if (in_array($mailboxResult['status'], ['valid', 'invalid'])) {
                        \App\Models\GlobalEmailCache::create([
                            'email' => $email,
                            'status' => $mailboxResult['status'],
                            'reason' => $mailboxResult['reason'] ?? null,
                        ]);
                    }
                }
                
                $validated++;

                if ($mailboxResult['status'] === 'valid') {
                    // Mailbox verified
                    $contact->markAsValid([
                        'verification_method' => 'third_party_api',
                        'note' => $mailboxResult['reason'] ?? null,
                    ]);
                    $valid++;
                } elseif (in_array($mailboxResult['status'], ['invalid', 'risky', 'unknown', 'timeout'])) {
                    if (in_array($mailboxResult['status'], ['invalid', 'timeout'])) {
                        // Mailbox doesn't exist, is definitely invalid, or connection repeatedly timed out
                        $contact->markAsInvalid([
                            'verification_method' => 'third_party_api',
                            'api_status' => $mailboxResult['status'],
                        ], $mailboxResult['reason'] ?? 'Invalid email');
                        $invalid++;
                    } else {
                        // It is risky or unknown. Give it ONE retry to bypass greylisting.
                        $result = $contact->validation_result ?? [];
                        $retries = $result['retry_count'] ?? 0;

                        if ($retries < 1) {
                            $result['retry_count'] = $retries + 1;
                            $result['reason'] = $mailboxResult['reason'] ?? 'Risky API status, will retry';
                            
                            $contact->update([
                                'validation_status' => Contact::STATUS_PENDING,
                                'validation_result' => $result,
                                'validation_error' => 'Temporarily skipped (risky)',
                            ]);
                            $skipped++;
                        } else {
                            // Tried again and it is still risky. Mark as definitively invalid.
                            $contact->markAsInvalid([
                                'verification_method' => 'third_party_api',
                                'api_status' => $mailboxResult['status'],
                                'retry_count' => $retries,
                            ], $mailboxResult['reason'] ?? 'Invalid / Risky email');
                            $invalid++;
                        }
                    }
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

