<?php

namespace App\Services;

use App\Models\SmtpConfig;
use Illuminate\Support\Collection;

class SmtpSelectorService
{
    /**
     * In-memory cache of active SMTP models keyed by user_id (or 'all') during a job cycle.
     */
    protected array $userSmtpCache = [];

    /**
     * Clear the in-memory SMTP cache.
     */
    public function clearCache(): void
    {
        $this->userSmtpCache = [];
    }

    /**
     * Get cached active SMTPs for a user (or all users if null).
     */
    protected function getActiveSmtps(?int $userId = null): Collection
    {
        $key = $userId ?? 'all';
        if (!isset($this->userSmtpCache[$key])) {
            $query = SmtpConfig::active();
            if ($userId) {
                $query->where('user_id', $userId);
            }
            $this->userSmtpCache[$key] = $query->get();
        }

        // Filter out any that were auto-paused in memory during this run
        return $this->userSmtpCache[$key]->filter(fn(SmtpConfig $s) => $s->is_active && !$s->auto_paused);
    }

    /**
     * Select a random active SMTP account that can still send.
     * If userId is provided, only select from that user's SMTPs.
     */
    public function selectSmtp(?int $userId = null): ?SmtpConfig
    {
        $smtps = $this->getActiveSmtps($userId);

        if ($smtps->isEmpty()) {
            return null;
        }

        $availableSmtps = $smtps->filter(function (SmtpConfig $smtp) {
            return $smtp->canSend();
        });

        if ($availableSmtps->isEmpty()) {
            return null;
        }

        return $availableSmtps->random();
    }

    /**
     * Get all active SMTPs allowed for the given Campaign (using eager-loaded relation when available).
     */
    public function getEligibleSmtpsForCampaign(\App\Models\Campaign $campaign): Collection
    {
        $smtps = $this->getActiveSmtps($campaign->user_id);

        if ($smtps->isEmpty()) {
            return collect();
        }

        if (!$campaign->use_all_smtps) {
            $allowedIds = $campaign->relationLoaded('smtpConfigs')
                ? $campaign->smtpConfigs->pluck('id')->all()
                : $campaign->smtpConfigs()->pluck('smtp_configs.id')->all();

            // Filter to specific attached SMTPs when present; if pivot table is empty (e.g. legacy/unattached campaign), fall back to user's active SMTP pool so sending never stalls
            if (!empty($allowedIds)) {
                $allowedMap = array_flip($allowedIds);
                $smtps = $smtps->filter(fn(SmtpConfig $s) => isset($allowedMap[$s->id]));
            }
        }

        return $smtps;
    }

    /**
     * Select a random active SMTP account that can still send,
     * specifically allowed by the given Campaign.
     */
    public function selectSmtpForCampaign(\App\Models\Campaign $campaign): ?SmtpConfig
    {
        $smtps = $this->getEligibleSmtpsForCampaign($campaign);

        if ($smtps->isEmpty()) {
            return null;
        }

        $availableSmtps = $smtps->filter(function (SmtpConfig $smtp) {
            return $smtp->canSend();
        });

        if ($availableSmtps->isEmpty()) {
            return null;
        }

        return $availableSmtps->random();
    }

    /**
     * Return minimum seconds until next SMTP is ready for this campaign:
     * - 0 if at least one SMTP can send immediately
     * - > 0 if SMTP(s) have quota left this hour/day but are waiting on intra-hour cooldown
     * - null if all allowed SMTPs are exhausted or outside active hours
     */
    public function getSecondsUntilNextSendForCampaign(\App\Models\Campaign $campaign): ?int
    {
        $smtps = $this->getEligibleSmtpsForCampaign($campaign);
        if ($smtps->isEmpty()) {
            return null;
        }

        $minWait = null;
        foreach ($smtps as $smtp) {
            $wait = $smtp->getSecondsUntilNextSend();
            if ($wait === 0) {
                return 0;
            }
            if ($wait !== null && ($minWait === null || $wait < $minWait)) {
                $minWait = $wait;
            }
        }

        return $minWait;
    }

    /**
     * Check if any allowed SMTP for the campaign can send.
     */
    public function canSendAnyForCampaign(\App\Models\Campaign $campaign): bool
    {
        return $this->selectSmtpForCampaign($campaign) !== null;
    }

    /**
     * Get all active SMTPs with their remaining capacity.
     */
    public function getAvailableSmtps(): Collection
    {
        return SmtpConfig::active()->get()->map(function (SmtpConfig $smtp) {
            return [
                'id' => $smtp->id,
                'name' => $smtp->name,
                'remaining' => $smtp->remaining_today,
                'can_send' => $smtp->canSend(),
            ];
        });
    }

    /**
     * Get the total remaining capacity across all active SMTPs.
     * Uses effective limit (respects warmup mode).
     */
    public function getTotalRemainingCapacity(): int
    {
        return SmtpConfig::active()->get()->sum(function (SmtpConfig $smtp) {
            $smtp->resetDailyCounterIfNeeded();
            return max(0, $smtp->getEffectiveLimit() - $smtp->sent_today);
        });
    }

    /**
     * Check if any SMTP can send.
     */
    public function canSendAny(?int $userId = null): bool
    {
        return $this->selectSmtp($userId) !== null;
    }

    /**
     * Reset all SMTP daily counters (usually called at midnight).
     * Properly progresses warmup days, rolls randomized daily limits, and auto-resumes quota-paused campaigns.
     */
    public function resetAllCounters(): void
    {
        foreach (SmtpConfig::all() as $smtp) {
            $smtp->resetDailyCounterIfNeeded();
        }

        $resumed = \App\Models\Campaign::where('status', \App\Models\Campaign::STATUS_PAUSED)
            ->where('pause_reason', \App\Models\Campaign::PAUSE_REASON_QUOTA)
            ->update([
                'status' => \App\Models\Campaign::STATUS_SENDING,
                'pause_reason' => null,
            ]);

        if ($resumed > 0) {
            \Illuminate\Support\Facades\Log::info("SmtpSelectorService: Auto-resumed {$resumed} quota-paused campaign(s) after daily counter reset.");
        }
        
        \Illuminate\Support\Facades\Log::info("SmtpSelectorService: Reset daily counters.");
    }
}
