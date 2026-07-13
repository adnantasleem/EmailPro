<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'recipient_id',
        'smtp_config_id',
        'subject_line_id',
        'body_template_id',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';

    /**
     * Get the campaign.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the recipient.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    /**
     * Get the SMTP config used.
     */
    public function smtpConfig(): BelongsTo
    {
        return $this->belongsTo(SmtpConfig::class);
    }

    /**
     * Get the subject line used.
     */
    public function subjectLine(): BelongsTo
    {
        return $this->belongsTo(SubjectLine::class);
    }

    /**
     * Get the body template used.
     */
    public function bodyTemplate(): BelongsTo
    {
        return $this->belongsTo(BodyTemplate::class);
    }

    /**
     * Create a success log entry.
     */
    public static function logSuccess(
        Campaign $campaign,
        Recipient $recipient,
        SmtpConfig $smtpConfig,
        SubjectLine $subjectLine,
        BodyTemplate $bodyTemplate
    ): self {
        return self::create([
            'campaign_id' => $campaign->id,
            'recipient_id' => $recipient->id,
            'smtp_config_id' => $smtpConfig->id,
            'subject_line_id' => $subjectLine->id,
            'body_template_id' => $bodyTemplate->id,
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    /**
     * Create a failure log entry.
     */
    public static function logFailure(
        Campaign $campaign,
        Recipient $recipient,
        SmtpConfig $smtpConfig,
        SubjectLine $subjectLine,
        BodyTemplate $bodyTemplate,
        string $errorMessage
    ): self {
        return self::create([
            'campaign_id' => $campaign->id,
            'recipient_id' => $recipient->id,
            'smtp_config_id' => $smtpConfig->id,
            'subject_line_id' => $subjectLine->id,
            'body_template_id' => $bodyTemplate->id,
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'sent_at' => now(),
        ]);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
