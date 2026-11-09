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
    protected int $maxExecutionTime = 12;
    protected float $startTime;

    /**
     * Create a new job instance.
     */
    public function __construct(int $maxExecutionTime = 12)
    {
        $this->maxExecutionTime = $maxExecutionTime;
    }

    /**
     * Execute the job.
     */
    public function handle(EmailValidatorService $validator): void
    {
        $this->startTime = microtime(true);

        // Find campaigns in validating or sending status that have pending recipients
        $campaigns = Campaign::whereIn('status', [Campaign::STATUS_VALIDATING, Campaign::STATUS_SENDING])
            ->whereHas('recipients', function ($q) {
                $q->whereIn('status', [Recipient::STATUS_PENDING, Recipient::STATUS_VALIDATING]);
            })
            ->get();

        if ($campaigns->isEmpty()) {
            return;
        }

        foreach ($campaigns as $campaign) {
            if ((microtime(true) - $this->startTime) >= $this->maxExecutionTime) {
                break;
            }
            $this->validateCampaignRecipients($campaign, $validator);
        }
    }

    /**
     * Validate recipients for a specific campaign.
     */
    protected function validateCampaignRecipients(Campaign $campaign, EmailValidatorService $validator): void
    {
        // Recover stuck validating recipients (> 5 minutes old)
        $campaign->recipients()
            ->where('status', Recipient::STATUS_VALIDATING)
            ->where('updated_at', '<', now()->subMinutes(5))
            ->update(['status' => Recipient::STATUS_PENDING]);

        // Get pending recipients
        $recipients = $campaign->recipients()
            ->where('status', Recipient::STATUS_PENDING)
            ->limit($this->batchSize)
            ->get();

        if ($recipients->isEmpty()) {
            $remainingPending = $campaign->recipients()->pendingValidation()->exists();

            if (!$remainingPending && $campaign->status === Campaign::STATUS_VALIDATING) {
                $hasValid = $campaign->recipients()->status(Recipient::STATUS_VALID)->exists();
                if ($hasValid) {
                    $campaign->update([
                        'status' => Campaign::STATUS_SENDING,
                        'started_at' => now(),
                    ]);
                    Log::info("ValidateEmailsJob: Campaign {$campaign->id} moved to sending status");
                }
            }
            return;
        }

        // Pre-attach campaign relation so markAsInvalid/markAsDisposable doesn't lazy-load it
        foreach ($recipients as $recipient) {
            $recipient->setRelation('campaign', $campaign);
        }

        $validated = 0;
        $valid = 0;
        $invalid = 0;

        foreach ($recipients as $recipient) {
            if ((microtime(true) - $this->startTime) >= $this->maxExecutionTime) {
                break;
            }

            $result = $validator->validate($recipient);
            $validated++;

            if ($result['is_valid']) {
                $valid++;
            } else {
                $invalid++;
            }
        }

        if ($validated > 0) {
            Log::info("ValidateEmailsJob: Campaign {$campaign->id} - Validated: {$validated}, Valid: {$valid}, Invalid: {$invalid}");
        }
    }
}
