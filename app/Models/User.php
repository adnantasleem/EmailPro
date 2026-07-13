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
        'monthly_email_limit',
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
            'monthly_email_limit' => 'integer',
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
     * Get remaining email quota for this month.
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
     * Check if user has reached their monthly email limit.
     */
    public function hasReachedEmailLimit(): bool
    {
        if (!$this->monthly_email_limit || $this->monthly_email_limit <= 0) {
            return false; // Unlimited
        }
        
        return $this->emails_sent_this_month >= $this->monthly_email_limit;
    }

    /**
     * Check if user can send specified number of emails.
     */
    public function canSendEmails(int $count = 1): bool
    {
        if (!$this->monthly_email_limit || $this->monthly_email_limit <= 0) {
            return true; // Unlimited
        }
        
        return ($this->emails_sent_this_month + $count) <= $this->monthly_email_limit;
    }
}
