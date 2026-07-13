<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SmtpConfig extends Model
{
    use HasFactory;

    /**
     * Maximum bounce rate before auto-pause (5%).
     */
    const MAX_BOUNCE_RATE = 5.0;

    /**
     * Warmup schedule: day range => daily limit
     */
    const WARMUP_SCHEDULE = [
        [1, 3, 20],      // Days 1-3: 20 emails
        [4, 7, 50],      // Days 4-7: 50 emails
        [8, 14, 100],    // Days 8-14: 100 emails
        [15, 21, 200],   // Days 15-21: 200 emails
        [22, 28, 400],   // Days 22-28: 400 emails
    ];

    protected $fillable = [
        'user_id',
        'name',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_email',
        'from_name',
        'daily_limit',
        'pacing_strategy',
        'min_emails_per_day',
        'max_emails_per_day',
        'current_daily_limit',
        'min_emails_per_hour',
        'max_emails_per_hour',
        'current_hourly_limit',
        'limit_calculated_at',
        'active_time_start',
        'active_time_end',
        'sent_today',
        'last_reset_date',
        'is_active',
        // Warmup fields
        'is_warming_up',
        'warmup_started_at',
        'warmup_day',
        'warmup_daily_limit',
        // Bounce tracking fields
        'total_sent',
        'total_bounced',
        'sent_last_hour',
        'bounced_last_hour',
        'last_hour_reset',
        'bounce_rate',
        'auto_paused',
        'paused_at',
        'pause_reason',
    ];

    protected $casts = [
        'port' => 'integer',
        'daily_limit' => 'integer',
        'min_emails_per_day' => 'integer',
        'max_emails_per_day' => 'integer',
        'current_daily_limit' => 'integer',
        'min_emails_per_hour' => 'integer',
        'max_emails_per_hour' => 'integer',
        'current_hourly_limit' => 'integer',
        'limit_calculated_at' => 'datetime',
        'sent_today' => 'integer',
        'is_active' => 'boolean',
        'last_reset_date' => 'date',
        // Warmup
        'is_warming_up' => 'boolean',
        'warmup_started_at' => 'date',
        'warmup_day' => 'integer',
        'warmup_daily_limit' => 'integer',
        // Bounce tracking
        'total_sent' => 'integer',
        'total_bounced' => 'integer',
        'sent_last_hour' => 'integer',
        'bounced_last_hour' => 'integer',
        'last_hour_reset' => 'datetime',
        'bounce_rate' => 'decimal:2',
        'auto_paused' => 'boolean',
        'paused_at' => 'datetime',
    ];

    /**
     * Get the password attribute (decrypted).
     */
    public function getDecryptedPasswordAttribute(): string
    {
        try {
            return Crypt::decryptString($this->password);
        } catch (\Exception $e) {
            return $this->password;
        }
    }

    /**
     * Set the password attribute (encrypted).
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = Crypt::encryptString($value);
    }

    /**
     * Get the user that owns this SMTP config.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get email logs sent through this SMTP.
     */
    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    // ========================================
    // SENDING LIMITS
    // ========================================

    /**
     * Get the effective daily limit (considering warmup mode).
     */
    public function getEffectiveLimit(): int
    {
        if ($this->is_warming_up) {
            return $this->warmup_daily_limit;
        }
        
        if ($this->pacing_strategy === 'per_day' && $this->current_daily_limit) {
            return $this->current_daily_limit;
        }
        
        return $this->daily_limit;
    }

    /**
     * Check if SMTP can send more emails right now.
     */
    public function canSend(): bool
    {
        $this->resetDailyCounterIfNeeded();
        $this->resetHourlyCountersIfNeeded();
        
        // Auto-resume after 24 hours if paused
        if ($this->auto_paused && $this->paused_at) {
            if ($this->paused_at->lt(now()->subHours(24))) {
                $this->autoResume();
            } else {
                return false;
            }
        } elseif ($this->auto_paused) {
            return false;
        }
        
        if (!$this->is_active) {
            return false;
        }

        // Active Time Check (e.g. only send between 09:00 and 17:00)
        if (!empty($this->active_time_start) && !empty($this->active_time_end)) {
            $nowTime = now()->format('H:i:s');
            // Handle times that span across midnight (e.g., 22:00 to 06:00)
            if ($this->active_time_start > $this->active_time_end) {
                if ($nowTime < $this->active_time_start && $nowTime > $this->active_time_end) {
                    return false;
                }
            } else {
                // Normal daytime shift
                if ($nowTime < $this->active_time_start || $nowTime > $this->active_time_end) {
                    return false;
                }
            }
        }

        $dailyLimit = $this->getEffectiveLimit();
        if ($this->sent_today >= $dailyLimit) {
            return false;
        }

        // Calculate dynamic hourly limit for this hour if needed
        $startOfHour = now()->startOfHour();
        if (!$this->limit_calculated_at || $this->limit_calculated_at->lt($startOfHour)) {
            if ($this->pacing_strategy === 'per_day') {
                // Determine active hours
                $activeHours = 24;
                if (!empty($this->active_time_start) && !empty($this->active_time_end)) {
                    $start = \Carbon\Carbon::parse($this->active_time_start);
                    $end = \Carbon\Carbon::parse($this->active_time_end);
                    if ($start->gt($end)) {
                        $activeHours = 24 - $start->diffInHours($end);
                    } else {
                        $activeHours = $end->diffInHours($start);
                    }
                    if ($activeHours <= 0) $activeHours = 24;
                }
                
                $hourlyAvg = $dailyLimit / $activeHours;
                $min = max(1, (int) floor($hourlyAvg));
                $max = max(1, (int) ceil($hourlyAvg));
            } else {
                $min = $this->min_emails_per_hour ?? max(1, (int) floor($dailyLimit / 24));
                $max = $this->max_emails_per_hour ?? max(1, (int) ceil($dailyLimit / 24));
            }
            
            // Ensure min is <= max
            if ($min > $max) {
                $min = $max;
            }

            $this->update([
                'current_hourly_limit' => rand($min, $max),
                'limit_calculated_at' => now(),
            ]);
        }

        $hourlyLimit = max(1, $this->current_hourly_limit);

        // Anti-Burst 1: Enforce hourly limit
        if ($this->sent_last_hour >= $hourlyLimit) {
            return false;
        }

        // Anti-Burst 2: Enforce pacing between emails (Intra-hour spacing)
        $minSecondsBetweenEmails = (int) floor(3600 / $hourlyLimit);
        $lastSentAt = \Illuminate\Support\Facades\Cache::get("smtp_{$this->id}_last_sent");
        
        if ($lastSentAt && (now()->timestamp - $lastSentAt) < $minSecondsBetweenEmails) {
            return false; // Waiting for the required time interval to pass
        }
        
        return true;
    }

    /**
     * Get remaining sends for today.
     */
    public function getRemainingTodayAttribute(): int
    {
        $this->resetDailyCounterIfNeeded();
        return max(0, $this->getEffectiveLimit() - $this->sent_today);
    }

    /**
     * Increment the sent counter.
     */
    public function incrementSentCount(): void
    {
        $this->increment('sent_today');
    }

    /**
     * Reset daily counter if it's a new day.
     */
    public function resetDailyCounterIfNeeded(): void
    {
        if ($this->last_reset_date === null || $this->last_reset_date->lt(today())) {
            
            // Roll new randomized daily limit if using per_day strategy
            $newDailyLimit = null;
            if ($this->pacing_strategy === 'per_day' && $this->min_emails_per_day && $this->max_emails_per_day) {
                $newDailyLimit = rand($this->min_emails_per_day, $this->max_emails_per_day);
            }

            $this->update([
                'sent_today' => 0,
                'last_reset_date' => today(),
                'current_daily_limit' => $newDailyLimit ?? $this->current_daily_limit,
            ]);
            
            // Progress warmup if in warmup mode
            if ($this->is_warming_up) {
                $this->progressWarmup();
            }

            // [NEW] Auto-resume paused campaigns for this user since capacity just opened up
            // Only resume campaigns that were paused due to quota exhaustion, NOT manually paused ones
            \App\Models\Campaign::where('user_id', $this->user_id)
                ->where('status', \App\Models\Campaign::STATUS_PAUSED)
                ->where('pause_reason', \App\Models\Campaign::PAUSE_REASON_QUOTA)
                ->update([
                    'status' => \App\Models\Campaign::STATUS_SENDING,
                    'pause_reason' => null,
                ]);
                
            \Illuminate\Support\Facades\Log::info("SmtpConfig [{$this->id}]: Reset daily counters and auto-resumed quota-paused campaigns for user {$this->user_id}.");
        }
    }

    /**
     * Scope to get only active SMTPs.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('auto_paused', false);
    }

    /**
     * Scope to get SMTPs that can send.
     */
    public function scopeCanSend($query)
    {
        return $query->active();
    }

    // ========================================
    // WARMUP METHODS
    // ========================================

    /**
     * Start warmup mode for this SMTP.
     */
    public function startWarmup(): void
    {
        $this->update([
            'is_warming_up' => true,
            'warmup_started_at' => today(),
            'warmup_day' => 1,
            'warmup_daily_limit' => 20, // Start with 20 emails/day
        ]);

        Log::info("SMTP [{$this->name}] started warmup mode");
    }

    /**
     * Progress to the next warmup day and update limit.
     */
    public function progressWarmup(): void
    {
        if (!$this->is_warming_up) {
            return;
        }

        $newDay = $this->warmup_day + 1;
        $newLimit = $this->getWarmupLimitForDay($newDay);

        // If we've completed the warmup schedule
        if ($newDay > 28) {
            $this->endWarmup();
            return;
        }

        $this->update([
            'warmup_day' => $newDay,
            'warmup_daily_limit' => $newLimit,
        ]);

        Log::info("SMTP [{$this->name}] warmup day {$newDay}, limit: {$newLimit}");
    }

    /**
     * Get the warmup limit for a specific day.
     */
    protected function getWarmupLimitForDay(int $day): int
    {
        foreach (self::WARMUP_SCHEDULE as [$startDay, $endDay, $limit]) {
            if ($day >= $startDay && $day <= $endDay) {
                return $limit;
            }
        }
        
        // After day 28, return full daily limit
        return $this->daily_limit;
    }

    /**
     * End warmup mode and use full daily limit.
     */
    public function endWarmup(): void
    {
        $this->update([
            'is_warming_up' => false,
            'warmup_started_at' => null,
            'warmup_day' => 0,
            'warmup_daily_limit' => 0,
        ]);

        Log::info("SMTP [{$this->name}] completed warmup, now using full limit: {$this->daily_limit}");
    }

    /**
     * Get warmup progress percentage.
     */
    public function getWarmupProgressAttribute(): int
    {
        if (!$this->is_warming_up) {
            return 100;
        }
        return min(100, round(($this->warmup_day / 28) * 100));
    }

    /**
     * Get warmup status text.
     */
    public function getWarmupStatusAttribute(): string
    {
        if (!$this->is_warming_up) {
            return 'Ready';
        }
        return "Day {$this->warmup_day}/28 ({$this->warmup_daily_limit}/day)";
    }

    // ========================================
    // BOUNCE TRACKING METHODS
    // ========================================

    /**
     * Record a successful send.
     */
    public function recordSend(): void
    {
        $this->resetHourlyCountersIfNeeded();
        
        $this->increment('total_sent');
        $this->increment('sent_last_hour');
        $this->increment('sent_today');
        
        // Record exact timestamp to enforce intra-hour pacing
        \Illuminate\Support\Facades\Cache::put("smtp_{$this->id}_last_sent", now()->timestamp, 3600);
        
        $this->recalculateBounceRate();
    }

    /**
     * Record a bounce/failure.
     */
    public function recordBounce(): void
    {
        $this->resetHourlyCountersIfNeeded();
        
        $this->increment('total_sent');  // Count as an attempt
        $this->increment('sent_last_hour'); // Count as an attempt
        $this->increment('total_bounced');
        $this->increment('bounced_last_hour');
        $this->increment('sent_today');  // Count bounces toward daily limit too
        
        $this->recalculateBounceRate();
        $this->checkAndAutoPause();
    }

    /**
     * Recalculate the bounce rate.
     */
    protected function recalculateBounceRate(): void
    {
        // Use hourly stats for recent performance
        $sent = $this->sent_last_hour;
        $bounced = $this->bounced_last_hour;
        
        if ($sent > 0) {
            $rate = ($bounced / $sent) * 100;
            $this->update(['bounce_rate' => round($rate, 2)]);
        }
    }

    /**
     * Check bounce rate and auto-pause if too high.
     */
    protected function checkAndAutoPause(): void
    {
        // Check hourly bounce rate if we've sent at least 5 emails
        if ($this->sent_last_hour >= 5 && $this->bounce_rate > self::MAX_BOUNCE_RATE) {
            $this->autoPause("Hourly bounce rate {$this->bounce_rate}% exceeds " . self::MAX_BOUNCE_RATE . "%");
            return;
        }

        // Also check overall bounce rate if we've sent at least 20 emails total
        if ($this->total_sent >= 20) {
            $overallRate = $this->overall_bounce_rate;
            if ($overallRate > self::MAX_BOUNCE_RATE) {
                $this->autoPause("Overall bounce rate {$overallRate}% exceeds " . self::MAX_BOUNCE_RATE . "%");
            }
        }
    }

    /**
     * Auto-pause this SMTP due to issues.
     */
    public function autoPause(string $reason): void
    {
        $this->update([
            'auto_paused' => true,
            'paused_at' => now(),
            'pause_reason' => $reason,
        ]);

        Log::warning("SMTP [{$this->name}] auto-paused: {$reason}");
    }

    /**
     * Resume a paused SMTP (manual).
     */
    public function resume(): void
    {
        $this->update([
            'auto_paused' => false,
            'paused_at' => null,
            'pause_reason' => null,
            // Reset hourly counters to give it a fresh start
            'sent_last_hour' => 0,
            'bounced_last_hour' => 0,
            'bounce_rate' => 0,
            'last_hour_reset' => now(),
        ]);

        Log::info("SMTP [{$this->name}] resumed manually");
    }

    /**
     * Auto-resume after 24 hours of pause.
     */
    public function autoResume(): void
    {
        $this->update([
            'auto_paused' => false,
            'paused_at' => null,
            'pause_reason' => null,
            // Reset all bounce tracking counters
            'sent_last_hour' => 0,
            'bounced_last_hour' => 0,
            'bounce_rate' => 0,
            'last_hour_reset' => now(),
            // Also reset total counters to give fresh start
            'total_sent' => 0,
            'total_bounced' => 0,
        ]);

        // Auto-resume any campaigns paused due to quota exhaustion for this user
        // Manually paused campaigns are NOT affected
        \App\Models\Campaign::where('user_id', $this->user_id)
            ->where('status', \App\Models\Campaign::STATUS_PAUSED)
            ->where('pause_reason', \App\Models\Campaign::PAUSE_REASON_QUOTA)
            ->update([
                'status' => \App\Models\Campaign::STATUS_SENDING,
                'pause_reason' => null,
            ]);

        Log::info("SMTP [{$this->name}] auto-resumed after 24 hours, quota-paused campaigns re-activated for user {$this->user_id}");
    }

    /**
     * Reset hourly counters if more than an hour has passed.
     */
    protected function resetHourlyCountersIfNeeded(): void
    {
        if ($this->last_hour_reset === null || $this->last_hour_reset->lt(now()->subHour())) {
            $this->update([
                'sent_last_hour' => 0,
                'bounced_last_hour' => 0,
                'bounce_rate' => 0,
                'last_hour_reset' => now(),
            ]);
        }
    }

    /**
     * Get the overall bounce rate (all-time).
     */
    public function getOverallBounceRateAttribute(): float
    {
        if ($this->total_sent === 0) {
            return 0;
        }
        return round(($this->total_bounced / $this->total_sent) * 100, 2);
    }

    /**
     * Get status badge info for display.
     */
    public function getStatusBadgeAttribute(): array
    {
        if ($this->auto_paused) {
            $resumeText = 'Auto-Paused';
            if ($this->paused_at) {
                $resumeAt = $this->paused_at->addHours(24);
                if ($resumeAt->isFuture()) {
                    $hoursLeft = now()->diffInHours($resumeAt);
                    $minutesLeft = now()->diffInMinutes($resumeAt) % 60;
                    $resumeText = $hoursLeft > 0
                        ? "Auto-Paused (resumes in {$hoursLeft}h {$minutesLeft}m)"
                        : "Auto-Paused (resumes in {$minutesLeft}m)";
                } else {
                    $resumeText = 'Auto-Paused (resuming soon)';
                }
            }
            return [
                'text' => $resumeText,
                'class' => 'bg-red-100 text-red-800',
                'icon' => '⚠️',
            ];
        }

        if ($this->is_warming_up) {
            return [
                'text' => $this->warmup_status,
                'class' => 'bg-yellow-100 text-yellow-800',
                'icon' => '🔥',
            ];
        }

        if (!$this->is_active) {
            return [
                'text' => 'Inactive',
                'class' => 'bg-gray-100 text-gray-800',
                'icon' => '⏸️',
            ];
        }

        return [
            'text' => 'Active',
            'class' => 'bg-green-100 text-green-800',
            'icon' => '✅',
        ];
    }
}
