<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'from_name',
        'reply_to',
        'status',
        'pause_reason',
        'emails_per_hour',
        'min_delay_seconds',
        'max_delay_seconds',
        'use_all_smtps',
        'scheduled_at',
        'started_at',
        'completed_at',
        'import_status',
        'imported_count',
    ];

    protected $casts = [
        'emails_per_hour' => 'integer',
        'min_delay_seconds' => 'integer',
        'max_delay_seconds' => 'integer',
        'use_all_smtps' => 'boolean',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_VALIDATING = 'validating';
    const STATUS_SENDING = 'sending';
    const STATUS_PAUSED = 'paused';
    const STATUS_COMPLETED = 'completed';

    // Pause reasons
    const PAUSE_REASON_MANUAL = 'manual';
    const PAUSE_REASON_QUOTA = 'quota_exhausted';

    /**
     * Get the user that owns this campaign.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all subject lines for this campaign.
     */
    public function subjectLines(): HasMany
    {
        return $this->hasMany(SubjectLine::class);
    }

    /**
     * Get all body templates for this campaign.
     */
    public function bodyTemplates(): HasMany
    {
        return $this->hasMany(BodyTemplate::class);
    }

    /**
     * Get all recipients for this campaign.
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(Recipient::class);
    }

    /**
     * Get all email logs for this campaign.
     */
    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    /**
     * Get contact lists used in this campaign.
     */
    public function contactLists(): BelongsToMany
    {
        return $this->belongsToMany(ContactList::class, 'campaign_contact_list')
            ->withTimestamps();
    }

    /**
     * Get the specific SMTP configs assigned to this campaign.
     */
    public function smtpConfigs(): BelongsToMany
    {
        return $this->belongsToMany(SmtpConfig::class, 'campaign_smtp_config')
            ->withTimestamps();
    }

    /**
     * Get a random subject line.
     */
    public function getRandomSubjectLine(): ?SubjectLine
    {
        return $this->subjectLines()->inRandomOrder()->first();
    }

    /**
     * Get a random body template.
     */
    public function getRandomBodyTemplate(): ?BodyTemplate
    {
        return $this->bodyTemplates()->inRandomOrder()->first();
    }

    /**
     * Calculate random delay between min and max.
     */
    public function getRandomDelay(): int
    {
        return rand($this->min_delay_seconds, $this->max_delay_seconds);
    }

    /**
     * Calculate batch size for sending (per minute).
     */
    public function getBatchSize(): int
    {
        return (int) ceil($this->emails_per_hour / 60);
    }

    /**
     * Check if campaign is ready to send.
     */
    public function isReadyToSend(): bool
    {
        if ($this->status !== self::STATUS_SENDING) {
            return false;
        }

        if ($this->scheduled_at && $this->scheduled_at->isFuture()) {
            return false;
        }

        return true;
    }

    /**
     * Get statistics for this campaign.
     */
    public function getStatsAttribute(): array
    {
        return [
            'total' => $this->recipients()->count(),
            'pending' => $this->recipients()->where('status', 'pending')->count(),
            'validating' => $this->recipients()->where('status', 'validating')->count(),
            'valid' => $this->recipients()->where('status', 'valid')->count(),
            'invalid' => $this->recipients()->whereIn('status', ['invalid', 'disposable'])->count(),
            'sent' => $this->recipients()->where('status', 'sent')->count(),
            'failed' => $this->recipients()->where('status', 'failed')->count(),
        ];
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
