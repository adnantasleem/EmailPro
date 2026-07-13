<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Recipient;
use App\Services\EmailValidatorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ValidateEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $batchSize = 50;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(EmailValidatorService $validator): void
    {
        // Find campaigns in validating status
        $campaigns = Campaign::status(Campaign::STATUS_VALIDATING)->get();

        if ($campaigns->isEmpty()) {
            Log::info('ValidateEmailsJob: No campaigns to validate');
            return;
        }

        foreach ($campaigns as $campaign) {
            $this->validateCampaignRecipients($campaign, $validator);
        }
    }

    /**
     * Validate recipients for a specific campaign.
     */
    protected function validateCampaignRecipients(Campaign $campaign, EmailValidatorService $validator): void
    {
        Log::info("ValidateEmailsJob: Processing campaign {$campaign->id} - {$campaign->name}");

        // Get pending recipients
        $recipients = $campaign->recipients()
            ->pendingValidation()
            ->limit($this->batchSize)
            ->get();

        if ($recipients->isEmpty()) {
            // No more pending recipients, check if we should move to sending
            $remainingPending = $campaign->recipients()->pendingValidation()->count();
            
            if ($remainingPending === 0) {
                $validCount = $campaign->recipients()->status(Recipient::STATUS_VALID)->count();
                
                if ($validCount > 0) {
                    $campaign->update([
                        'status' => Campaign::STATUS_SENDING,
                        'started_at' => now(),
                    ]);
                    Log::info("ValidateEmailsJob: Campaign {$campaign->id} moved to sending status");
                } else {
                    Log::warning("ValidateEmailsJob: Campaign {$campaign->id} has no valid recipients");
                }
            }
            return;
        }

        // Mark recipients as validating
        $recipientIds = $recipients->pluck('id')->toArray();
        Recipient::whereIn('id', $recipientIds)->update(['status' => Recipient::STATUS_VALIDATING]);

        // Validate each recipient
        $validated = 0;
        $valid = 0;
        $invalid = 0;

        foreach ($recipients as $recipient) {
            $result = $validator->validate($recipient);
            $validated++;

            if ($result['is_valid']) {
                $valid++;
            } else {
                $invalid++;
            }
        }

        Log::info("ValidateEmailsJob: Campaign {$campaign->id} - Validated: {$validated}, Valid: {$valid}, Invalid: {$invalid}");
    }
}
