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
        'use_all_smtps',
        'scheduled_at',
        'started_at',
        'completed_at',
        'import_status',
        'imported_count',
    ];

    protected $casts = [
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
     * Get statistics for this campaign in a single aggregated query.
     */
    public function getStatsAttribute(): array
    {
        $bounceLike = "(error_message LIKE '%bounce%' OR error_message LIKE '%rejected%' OR error_message LIKE '%550%' OR error_message LIKE '%551%' OR error_message LIKE '%552%' OR error_message LIKE '%553%' OR error_message LIKE '%554%' OR error_message LIKE '%5.1.1%' OR error_message LIKE '%not found%' OR error_message LIKE '%does not exist%' OR error_message LIKE '%unavailable%' OR error_message LIKE '%undeliverable%' OR error_message LIKE '%unroutable%')";

        $row = $this->recipients()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status = 'validating' THEN 1 ELSE 0 END) as validating")
            ->selectRaw("SUM(CASE WHEN status = 'valid' THEN 1 ELSE 0 END) as valid")
            ->selectRaw("SUM(CASE WHEN status IN ('invalid', 'disposable') THEN 1 ELSE 0 END) as invalid")
            ->selectRaw("SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent")
            ->selectRaw("SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied")
            ->selectRaw("SUM(CASE WHEN status = 'failed' AND (error_message IS NULL OR NOT {$bounceLike}) THEN 1 ELSE 0 END) as failed")
            ->selectRaw("SUM(CASE WHEN status = 'bounced' OR (status = 'failed' AND {$bounceLike}) THEN 1 ELSE 0 END) as bounced")
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'validating' => (int) ($row->validating ?? 0),
            'valid' => (int) ($row->valid ?? 0),
            'invalid' => (int) ($row->invalid ?? 0),
            'sent' => (int) ($row->sent ?? 0),
            'replied' => (int) ($row->replied ?? 0),
            'failed' => (int) ($row->failed ?? 0),
            'bounced' => (int) ($row->bounced ?? 0),
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
