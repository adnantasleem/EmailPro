<?php

namespace App\Services;

use App\Models\SmtpConfig;
use Illuminate\Support\Facades\Log;

class WarmupService
{
    /**
     * Progress all warming SMTPs to the next day.
     * This should be called daily by a scheduled command.
     */
    public function progressAllWarmups(): array
    {
        $results = [
            'processed' => 0,
            'completed' => 0,
            'progressed' => 0,
        ];

        $warmingSmtps = SmtpConfig::where('is_warming_up', true)->get();

        foreach ($warmingSmtps as $smtp) {
            $results['processed']++;
            
            $wasWarmingUp = $smtp->is_warming_up;
            $smtp->progressWarmup();
            $smtp->refresh();

            if ($wasWarmingUp && !$smtp->is_warming_up) {
                $results['completed']++;
            } else {
                $results['progressed']++;
            }
        }

        Log::info("WarmupService: Processed {$results['processed']} SMTPs, {$results['progressed']} progressed, {$results['completed']} completed");

        return $results;
    }

    /**
     * Start warmup for a specific SMTP.
     */
    public function startWarmup(SmtpConfig $smtp): void
    {
        $smtp->startWarmup();
    }

    /**
     * End warmup for a specific SMTP.
     */
    public function endWarmup(SmtpConfig $smtp): void
    {
        $smtp->endWarmup();
    }

    /**
     * Get warmup status for all user's SMTPs.
     */
    public function getWarmupStatus(int $userId): array
    {
        $smtps = SmtpConfig::where('user_id', $userId)->get();
        
        return $smtps->map(function (SmtpConfig $smtp) {
            return [
                'id' => $smtp->id,
                'name' => $smtp->name,
                'is_warming_up' => $smtp->is_warming_up,
                'warmup_day' => $smtp->warmup_day,
                'warmup_progress' => $smtp->warmup_progress,
                'warmup_status' => $smtp->warmup_status,
                'effective_limit' => $smtp->getEffectiveLimit(),
                'daily_limit' => $smtp->daily_limit,
            ];
        })->toArray();
    }

    /**
     * Get recommended warmup duration based on target volume.
     */
    public function getRecommendedWarmupDuration(int $targetDailyVolume): array
    {
        $schedule = SmtpConfig::WARMUP_SCHEDULE;
        $daysNeeded = 0;

        foreach ($schedule as [$startDay, $endDay, $limit]) {
            if ($limit >= $targetDailyVolume) {
                $daysNeeded = $startDay;
                break;
            }
            $daysNeeded = $endDay;
        }

        // If target is higher than schedule max, need full 28 days
        if ($daysNeeded === 0) {
            $daysNeeded = 28;
        }

        return [
            'days_needed' => $daysNeeded,
            'schedule' => $schedule,
        ];
    }
}
