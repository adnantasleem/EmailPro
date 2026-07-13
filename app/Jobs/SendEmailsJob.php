<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Services\EmailSenderService;
use App\Services\SmtpSelectorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendEmailsJob FAILED permanently after all retries: ' . $exception->getMessage());
    }

    /**
     * Execute the job.
     */
    public function handle(EmailSenderService $sender, SmtpSelectorService $smtpSelector): void
    {
        // Check if any SMTP can send
        if (!$smtpSelector->canSendAny()) {
            Log::warning('SendEmailsJob: No SMTP accounts available to send');
            return;
        }

        // Find campaigns in sending status that are ready
        $campaigns = Campaign::status(Campaign::STATUS_SENDING)->get();

        if ($campaigns->isEmpty()) {
            Log::info('SendEmailsJob: No campaigns to send');
            return;
        }

        foreach ($campaigns as $campaign) {
            if (!$campaign->isReadyToSend()) {
                Log::info("SendEmailsJob: Campaign {$campaign->id} not ready (scheduled for {$campaign->scheduled_at})");
                continue;
            }

            $this->sendCampaignEmails($campaign, $sender, $smtpSelector);

            // Check if we've exhausted all SMTPs
            if (!$smtpSelector->canSendAny()) {
                Log::warning('SendEmailsJob: All SMTP accounts exhausted, stopping');
                break;
            }
        }
    }

    /**
     * Send emails for a specific campaign.
     */
    protected function sendCampaignEmails(
        Campaign $campaign,
        EmailSenderService $sender,
        SmtpSelectorService $smtpSelector
    ): void {
        Log::info("SendEmailsJob: Processing campaign {$campaign->id} - {$campaign->name}");

        // Calculate batch size (emails per minute = emails_per_hour / 60)
        $batchSize = $campaign->getBatchSize();

        // Send the batch
        $results = $sender->sendBatch($campaign, $batchSize);

        Log::info("SendEmailsJob: Campaign {$campaign->id} - Sent: {$results['sent']}, Failed: {$results['failed']}");

        // Log any failures for debugging
        foreach ($results['details'] as $detail) {
            if (!$detail['success']) {
                Log::error("SendEmailsJob: Failed to send to {$detail['email']}: {$detail['error']}");
            }
        }
    }
}
