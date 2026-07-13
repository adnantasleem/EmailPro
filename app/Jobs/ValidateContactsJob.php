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
        $noDns = 0;

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

            foreach ($contacts as $contact) {
                $contact->refresh();
                $email = strtolower($contact->email);
                $domain = substr(strrchr($email, '@'), 1);

                // Step 1: DNS/MX record check
                $hasMx = false;
                $mxhosts = [];
                if (@getmxrr($domain, $mxhosts)) {
                    $hasMx = count($mxhosts) > 0;
                }
                if (!$hasMx) {
                    // Check A record as fallback
                    $hasMx = @dns_get_record($domain, DNS_A) ? true : false;
                }
                
                if (!$hasMx) {
                    $contact->markAsInvalid([
                        'dns_verified' => false,
                    ], 'Domain has no mail server (MX records)');
                    $noDns++;
                    $validated++;
                    continue;
                }

                // Step 2: Typo detection
                $typoCheck = $validator->getKnownTypos();
                if (isset($typoCheck[$domain])) {
                    $contact->markAsInvalid([
                        'typo_detected' => true,
                        'suggested_domain' => $typoCheck[$domain],
                    ], "Typo detected. Did you mean: {$typoCheck[$domain]}?");
                    $invalid++;
                    $validated++;
                    continue;
                }

                // Step 3: SMTP mailbox check
                $mailboxResult = $this->checkMailbox($validator, $email, $domain);
                $validated++;

                if ($mailboxResult['exists'] === true) {
                    // Mailbox verified
                    $contact->markAsValid([
                        'dns_verified' => true,
                        'mailbox_verified' => true,
                        'verification_method' => 'smtp',
                    ]);
                    $valid++;
                } elseif ($mailboxResult['exists'] === false) {
                    // Mailbox doesn't exist
                    $contact->markAsInvalid([
                        'dns_verified' => true,
                        'mailbox_verified' => false,
                        'verification_method' => 'smtp',
                    ], $mailboxResult['reason'] ?? 'Mailbox does not exist');
                    $invalid++;
                } else {
                    // Inconclusive (null) - mark as valid (give benefit of doubt)
                    $contact->markAsValid([
                        'dns_verified' => true,
                        'mailbox_verified' => null,
                        'verification_method' => 'skipped',
                        'reason' => $mailboxResult['reason'] ?? 'SMTP check skipped or inconclusive',
                    ]);
                    $skipped++;
                }
            }
        }

        Log::info("ValidateContactsJob: List {$this->contactList->id} - Verified: {$validated}, Valid: {$valid}, Invalid: {$invalid}, No DNS: {$noDns}, Skipped: {$skipped}");
    }

    /**
     * Check mailbox using SMTP verification.
     */
    protected function checkMailbox(EmailValidatorService $validator, string $email, string $domain): array
    {
        // Use reflection to access protected method, or we add a public method
        // For now, let's use the validateEmail method and extract mailbox_exists
        $result = $validator->validateEmail($email);
        
        return [
            'exists' => $result['mailbox_exists'] ?? null,
            'reason' => $result['reason'] ?? null,
        ];
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

