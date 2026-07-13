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
        // Run for 55 seconds to stay within the minute interval
        $endTime = microtime(true) + 55;
        
        while (microtime(true) < $endTime) {
            $sentAny = false;
            
            // Check if any SMTP can send
            if (!$smtpSelector->canSendAny()) {
                sleep(1);
                continue;
            }

            // Find campaigns in sending status that are ready
            $campaigns = Campaign::status(Campaign::STATUS_SENDING)->get();

            if ($campaigns->isEmpty()) {
                break; // No active campaigns, stop job
            }

            foreach ($campaigns as $campaign) {
                if (!$campaign->isReadyToSend()) {
                    continue;
                }

                while ($smtpSelector->canSendAnyForCampaign($campaign)) {
                    $recipient = $campaign->recipients()->readyToSend()->first();
                    
                    if (!$recipient) {
                        // Mark campaign as completed if no recipients left
                        $remainingUnsent = $campaign->recipients()
                            ->whereIn('status', [
                                \App\Models\Recipient::STATUS_VALID,
                                \App\Models\Recipient::STATUS_PENDING,
                                \App\Models\Recipient::STATUS_VALIDATING,
                                \App\Models\Recipient::STATUS_FAILED,
                            ])
                            ->count();
                            
                        if ($remainingUnsent === 0) {
                            $campaign->update([
                                'status' => Campaign::STATUS_COMPLETED,
                                'completed_at' => now(),
                            ]);
                            Log::info("SendEmailsJob: Campaign {$campaign->id} completed.");
                        }
                        break; // Move to next campaign
                    }

                    // Send email (this puts SMTP on micro-cooldown)
                    $result = $sender->sendEmail($campaign, $recipient);
                    $sentAny = true;
                    
                    if (!$result['success']) {
                        Log::error("SendEmailsJob: Failed to send to {$recipient->email}: {$result['error']}");
                    }

                    // Break early if we've reached our 55s limit
                    if (microtime(true) >= $endTime) {
                        break 3;
                    }
                }
            }

            if (!$sentAny) {
                sleep(1); // Rest for 1 second before checking cooldowns again
            }
        }
    }
}
