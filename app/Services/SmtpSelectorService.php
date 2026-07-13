<?php

namespace App\Services;

use App\Models\SmtpConfig;
use Illuminate\Support\Collection;

class SmtpSelectorService
{
    /**
     * Select a random active SMTP account that can still send.
     * If userId is provided, only select from that user's SMTPs.
     */
    public function selectSmtp(?int $userId = null): ?SmtpConfig
    {
        // Get all active SMTPs (optionally filtered by user)
        $query = SmtpConfig::active();
        if ($userId) {
            $query->where('user_id', $userId);
        }
        $smtps = $query->get();

        if ($smtps->isEmpty()) {
            return null;
        }

        // Reset counters if needed and filter those under limit
        $availableSmtps = $smtps->filter(function (SmtpConfig $smtp) {
            return $smtp->canSend();
        });

        if ($availableSmtps->isEmpty()) {
            return null;
        }

        // Return a random one from available
        return $availableSmtps->random();
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
     * Also auto-resumes campaigns that were paused due to quota exhaustion.
     */
    public function resetAllCounters(): void
    {
        SmtpConfig::query()->update([
            'sent_today' => 0,
            'last_reset_date' => today(),
        ]);

        // Auto-resume all campaigns paused due to quota exhaustion
        // Now that quotas are reset, these campaigns can send again
        // Manually paused campaigns are NOT affected
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
