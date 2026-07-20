<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'daily_email_limit',
        'monthly_email_limit',
        'yearly_email_limit',
        'expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'daily_email_limit' => 'integer',
            'monthly_email_limit' => 'integer',
            'yearly_email_limit' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get all campaigns for this user.
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }
    
    /**
     * Check if user account is expired.
     */
    public function isAccountExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        return now()->greaterThan($this->expires_at);
    }

    /**
     * Get count of emails sent today by this user.
     */
    public function getEmailsSentThisDayAttribute(): int
    {
        return EmailLog::whereHas('campaign', function ($query) {
            $query->where('user_id', $this->id);
        })
        ->where('status', EmailLog::STATUS_SENT)
        ->where('sent_at', '>=', now()->startOfDay())
        ->count();
    }

    /**
     * Get count of emails sent this month by this user.
     */
    public function getEmailsSentThisMonthAttribute(): int
    {
        return EmailLog::whereHas('campaign', function ($query) {
            $query->where('user_id', $this->id);
        })
        ->where('status', EmailLog::STATUS_SENT)
        ->where('sent_at', '>=', now()->startOfMonth())
        ->count();
    }
    
    /**
     * Get count of emails sent this year by this user.
     */
    public function getEmailsSentThisYearAttribute(): int
    {
        return EmailLog::whereHas('campaign', function ($query) {
            $query->where('user_id', $this->id);
        })
        ->where('status', EmailLog::STATUS_SENT)
        ->where('sent_at', '>=', now()->startOfYear())
        ->count();
    }

    /**
     * Get remaining daily email quota.
     */
    public function getRemainingDailyEmailQuotaAttribute(): ?int
    {
        if (!$this->daily_email_limit || $this->daily_email_limit <= 0) {
            return null; // Unlimited
        }
        
        $remaining = $this->daily_email_limit - $this->emails_sent_this_day;
        return max(0, $remaining);
    }

    /**
     * Get remaining monthly email quota.
     */
    public function getRemainingEmailQuotaAttribute(): ?int
    {
        if (!$this->monthly_email_limit || $this->monthly_email_limit <= 0) {
            return null; // Unlimited
        }
        
        $remaining = $this->monthly_email_limit - $this->emails_sent_this_month;
        return max(0, $remaining);
    }
    
    /**
     * Get remaining yearly email quota.
     */
    public function getRemainingYearlyEmailQuotaAttribute(): ?int
    {
        if (!$this->yearly_email_limit || $this->yearly_email_limit <= 0) {
            return null; // Unlimited
        }
        
        $remaining = $this->yearly_email_limit - $this->emails_sent_this_year;
        return max(0, $remaining);
    }

    /**
     * Check if user has reached their daily email limit.
     */
    public function hasReachedDailyEmailLimit(): bool
    {
        if (!$this->daily_email_limit || $this->daily_email_limit <= 0) {
            return false;
        }
        return $this->emails_sent_this_day >= $this->daily_email_limit;
    }

    /**
     * Check if user has reached their monthly email limit.
     */
    public function hasReachedEmailLimit(): bool
    {
        if (!$this->monthly_email_limit || $this->monthly_email_limit <= 0) {
            return false;
        }
        return $this->emails_sent_this_month >= $this->monthly_email_limit;
    }
    
    /**
     * Check if user has reached their yearly email limit.
     */
    public function hasReachedYearlyEmailLimit(): bool
    {
        if (!$this->yearly_email_limit || $this->yearly_email_limit <= 0) {
            return false;
        }
        return $this->emails_sent_this_year >= $this->yearly_email_limit;
    }

    /**
     * Check if user can send specified number of emails.
     */
    public function canSendEmails(int $count = 1): bool
    {
        if ($this->isAccountExpired()) {
            return false;
        }

        if ($this->daily_email_limit && $this->daily_email_limit > 0) {
            if (($this->emails_sent_this_day + $count) > $this->daily_email_limit) {
                return false;
            }
        }

        if ($this->monthly_email_limit && $this->monthly_email_limit > 0) {
            if (($this->emails_sent_this_month + $count) > $this->monthly_email_limit) {
                return false;
            }
        }
        
        if ($this->yearly_email_limit && $this->yearly_email_limit > 0) {
            if (($this->emails_sent_this_year + $count) > $this->yearly_email_limit) {
                return false;
            }
        }
        
        return true;
    }
}
