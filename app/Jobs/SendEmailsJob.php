<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Recipient;
use App\Services\EmailSenderService;
use App\Services\SmtpSelectorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendEmailsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 2;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * Unique lock TTL in seconds to prevent duplicate queued jobs on shared hosting.
     */
    public int $uniqueFor = 60;

    /**
     * Maximum execution time budget in seconds per run.
     */
    protected int $maxExecutionTime;

    /**
     * Create a new job instance.
     */
    public function __construct(int $maxExecutionTime = 25)
    {
        $this->maxExecutionTime = $maxExecutionTime;
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
        $endTime = microtime(true) + $this->maxExecutionTime;

        // 1. Eager-load active sending campaigns once (exits in 1 query if no campaign is sending)
        $campaigns = Campaign::with(['user', 'smtpConfigs', 'subjectLines', 'bodyTemplates'])
            ->status(Campaign::STATUS_SENDING)
            ->get()
            ->filter(fn(Campaign $campaign) => $campaign->isReadyToSend())
            ->keyBy('id');

        if ($campaigns->isEmpty()) {
            return;
        }

        // Self-heal any unsent recipient in active campaigns that was prematurely marked 'replied' before sending
        Recipient::whereIn('campaign_id', $campaigns->keys())
            ->where('status', Recipient::STATUS_REPLIED)
            ->whereNull('sent_at')
            ->update([
                'status' => Recipient::STATUS_VALID,
                'replied_at' => null,
            ]);

        // Share the same User model instance across campaigns of the same user for accurate in-memory quota tracking
        $usersById = [];
        foreach ($campaigns as $campaign) {
            if ($campaign->user) {
                if (!isset($usersById[$campaign->user_id])) {
                    $usersById[$campaign->user_id] = $campaign->user;
                } else {
                    $campaign->setRelation('user', $usersById[$campaign->user_id]);
                }
            }
        }

        $smtpSelector->clearCache();

        // Buffer of ready recipients per campaign to avoid 1 SELECT query per email
        $recipientBuffers = [];

        while (microtime(true) < $endTime && $campaigns->isNotEmpty()) {
            $sentAny = false;
            $minWaitSeconds = null;

            foreach ($campaigns as $campaignId => $campaign) {
                if (microtime(true) >= $endTime) {
                    break 2;
                }

                // Skip campaign if user has reached their quota
                if ($campaign->user && !$campaign->user->canSendEmails(1)) {
                    continue;
                }

                // Refill recipient buffer in batches of 50 before selecting an SMTP so completed campaigns finish immediately
                if (empty($recipientBuffers[$campaignId])) {
                    $recipientBuffers[$campaignId] = $campaign->recipients()
                        ->readyToSend()
                        ->orderBy('id', 'asc')
                        ->limit(50)
                        ->get()
                        ->all();
                }

                if (empty($recipientBuffers[$campaignId])) {
                    $hasRemainingUnsent = $campaign->recipients()
                        ->whereIn('status', [
                            Recipient::STATUS_VALID,
                            Recipient::STATUS_PENDING,
                            Recipient::STATUS_VALIDATING,
                        ])
                        ->exists();

                    if (!$hasRemainingUnsent) {
                        $campaign->update([
                            'status' => Campaign::STATUS_COMPLETED,
                            'completed_at' => now(),
                        ]);
                        Log::info("SendEmailsJob: Campaign {$campaign->id} completed.");
                    }

                    $campaigns->forget($campaignId);
                    continue;
                }

                while (microtime(true) < $endTime && ($smtp = $smtpSelector->selectSmtpForCampaign($campaign)) !== null) {
                    // Refill recipient buffer in batches of 50 when empty
                    if (empty($recipientBuffers[$campaignId])) {
                        $recipientBuffers[$campaignId] = $campaign->recipients()
                            ->readyToSend()
                            ->orderBy('id', 'asc')
                            ->limit(50)
                            ->get()
                            ->all();
                    }

                    /** @var ?Recipient $recipient */
                    $recipient = array_shift($recipientBuffers[$campaignId]);

                    if (!$recipient) {
                        // No valid recipients left; check if any pending/validating recipients remain
                        $hasRemainingUnsent = $campaign->recipients()
                            ->whereIn('status', [
                                Recipient::STATUS_VALID,
                                Recipient::STATUS_PENDING,
                                Recipient::STATUS_VALIDATING,
                            ])
                            ->exists();

                        if (!$hasRemainingUnsent) {
                            $campaign->update([
                                'status' => Campaign::STATUS_COMPLETED,
                                'completed_at' => now(),
                            ]);
                            Log::info("SendEmailsJob: Campaign {$campaign->id} completed.");
                        }

                        // Remove this campaign from active loop for the remainder of this run
                        $campaigns->forget($campaignId);
                        break;
                    }

                    $result = $sender->sendEmail($campaign, $recipient, $smtp);
                    $sentAny = true;

                    if (!$result['success']) {
                        Log::error("SendEmailsJob: Failed to send to {$recipient->email}: {$result['error']}");
                    }

                    // Re-check user quota after send
                    if ($campaign->user && !$campaign->user->canSendEmails(1)) {
                        break;
                    }
                }

                // If campaign is still active, check when its next SMTP will come off cooldown
                if ($campaigns->has($campaignId)) {
                    $wait = $smtpSelector->getSecondsUntilNextSendForCampaign($campaign);
                    if ($wait !== null && ($minWaitSeconds === null || $wait < $minWaitSeconds)) {
                        $minWaitSeconds = $wait;
                    }
                }
            }

            if ($campaigns->isEmpty()) {
                break;
            }

            if (!$sentAny) {
                // If no SMTPs can send (all quotas exhausted or outside active hours), exit immediately
                if ($minWaitSeconds === null) {
                    break;
                }

                // If the next SMTP cooldown finishes after our job's time budget, exit immediately instead of sleeping
                $remainingTime = $endTime - microtime(true);
                if ($minWaitSeconds >= $remainingTime) {
                    break;
                }

                // Sleep the exact cooldown duration (capped at 5s per sleep step) with 0 DB queries
                $sleepSeconds = max(1, min((int) $minWaitSeconds, 5));
                sleep($sleepSeconds);
            }
        }
    }
}
