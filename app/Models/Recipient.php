<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\InvalidEmail;

class Recipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'email',
        'name',
        'custom_fields',
        'status',
        'unsubscribe_token',
        'validation_result',
        'validated_at',
        'sent_at',
        'error_message',
        'opened_at',
        'open_count',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'validation_result' => 'array',
        'validated_at' => 'datetime',
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'replied_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_VALIDATING = 'validating';
    const STATUS_VALID = 'valid';
    const STATUS_INVALID = 'invalid';
    const STATUS_DISPOSABLE = 'disposable';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_BOUNCED = 'bounced';
    const STATUS_REPLIED = 'replied';
    const STATUS_UNSUBSCRIBED = 'unsubscribed';

    /**
     * Get the campaign that owns this recipient.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get email logs for this recipient.
     */
    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    /**
     * Mark recipient as valid.
     */
    public function markAsValid(array $validationResult = []): void
    {
        $this->update([
            'status' => self::STATUS_VALID,
            'validation_result' => $validationResult,
            'validated_at' => now(),
        ]);
    }

    /**
     * Mark recipient as invalid - adds to blocklist and removes from campaign.
     */
    public function markAsInvalid(array $validationResult = [], string $reason = ''): void
    {
        // Add to global invalid emails blocklist
        $userId = $this->campaign->user_id;
        InvalidEmail::addEmail($userId, $this->email, $reason ?: 'Invalid email');

        // Delete from campaign (will be blocked on future imports)
        $this->delete();
    }

    /**
     * Mark recipient as disposable - adds to blocklist and removes from campaign.
     */
    public function markAsDisposable(array $validationResult = []): void
    {
        // Add to global invalid emails blocklist
        $userId = $this->campaign->user_id;
        InvalidEmail::addEmail($userId, $this->email, 'Disposable email');

        // Delete from campaign (will be blocked on future imports)
        $this->delete();
    }

    /**
     * Mark recipient as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark recipient as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Mark recipient as bounced.
     */
    public function markAsBounced(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_BOUNCED,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Determine if an error message indicates a bounce delivery failure.
     */
    public static function isBounceError(?string $errorMessage): bool
    {
        if (empty($errorMessage)) {
            return false;
        }

        $error = strtolower($errorMessage);
        
        // Bounce keywords and phrases
        $bounceKeywords = [
            'bounce',
            'bounced',
            'rejected',
            'user unknown',
            'unknown user',
            'no such user',
            'user not found',
            'recipient not found',
            'account not found',
            'mailbox unavailable',
            'mailbox not found',
            'mailbox full',
            'mailbox disabled',
            'invalid recipient',
            'invalid email',
            'invalid address',
            'unroutable',
            'undeliverable',
            'returned to sender',
            'domain not found',
            'user suspended',
            'account disabled',
            'does not exist',
            'no mx record',
            '550',
            '551',
            '552',
            '553',
            '554',
            '5.1.1',
            '5.1.2',
            '5.2.1',
            '5.7.1',
        ];

        foreach ($bounceKeywords as $keyword) {
            if (str_contains($error, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if this recipient is bounced (either by status or by error message pattern).
     */
    public function isBounced(): bool
    {
        if ($this->status === self::STATUS_BOUNCED) {
            return true;
        }

        if ($this->status === self::STATUS_FAILED && self::isBounceError($this->error_message)) {
            return true;
        }

        return false;
    }

    /**
     * Scope to get bounced recipients (status = bounced OR status = failed with bounce error).
     */
    public function scopeBounced($query)
    {
        return $query->where(function ($q) {
            $q->where('status', self::STATUS_BOUNCED)
              ->orWhere(function ($q2) {
                  $q2->where('status', self::STATUS_FAILED)
                     ->where(function ($q3) {
                         $q3->where('error_message', 'LIKE', '%bounce%')
                            ->orWhere('error_message', 'LIKE', '%rejected%')
                            ->orWhere('error_message', 'LIKE', '%550%')
                            ->orWhere('error_message', 'LIKE', '%551%')
                            ->orWhere('error_message', 'LIKE', '%552%')
                            ->orWhere('error_message', 'LIKE', '%553%')
                            ->orWhere('error_message', 'LIKE', '%554%')
                            ->orWhere('error_message', 'LIKE', '%5.1.1%')
                            ->orWhere('error_message', 'LIKE', '%not found%')
                            ->orWhere('error_message', 'LIKE', '%does not exist%')
                            ->orWhere('error_message', 'LIKE', '%unavailable%')
                            ->orWhere('error_message', 'LIKE', '%undeliverable%')
                            ->orWhere('error_message', 'LIKE', '%unroutable%');
                     });
              });
        });
    }

    /**
     * Scope to get failed recipients excluding bounces.
     */
    public function scopeFailedNonBounce($query)
    {
        return $query->where('status', self::STATUS_FAILED)
            ->where(function ($q) {
                $q->whereNull('error_message')
                  ->orWhere(function ($q2) {
                      $q2->where('error_message', 'NOT LIKE', '%bounce%')
                         ->where('error_message', 'NOT LIKE', '%rejected%')
                         ->where('error_message', 'NOT LIKE', '%550%')
                         ->where('error_message', 'NOT LIKE', '%551%')
                         ->where('error_message', 'NOT LIKE', '%552%')
                         ->where('error_message', 'NOT LIKE', '%553%')
                         ->where('error_message', 'NOT LIKE', '%554%')
                         ->where('error_message', 'NOT LIKE', '%5.1.1%')
                         ->where('error_message', 'NOT LIKE', '%not found%')
                         ->where('error_message', 'NOT LIKE', '%does not exist%')
                         ->where('error_message', 'NOT LIKE', '%unavailable%')
                         ->where('error_message', 'NOT LIKE', '%undeliverable%')
                         ->where('error_message', 'NOT LIKE', '%unroutable%');
                  });
            });
    }

    /**
     * Scope to get pending recipients for validation.
     */
    public function scopePendingValidation($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_VALIDATING]);
    }

    /**
     * Scope to get valid recipients ready to send.
     */
    public function scopeReadyToSend($query)
    {
        return $query->where('status', self::STATUS_VALID);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Generate or get unsubscribe token.
     */
    public function getUnsubscribeToken(): string
    {
        if (!$this->unsubscribe_token) {
            $this->unsubscribe_token = bin2hex(random_bytes(32));
            $this->save();
        }
        return $this->unsubscribe_token;
    }

    /**
     * Get the unsubscribe URL for this recipient.
     */
    public function getUnsubscribeUrl(): string
    {
        return route('public.unsubscribe', $this->getUnsubscribeToken());
    }

    /**
     * Get the tracking pixel URL for this recipient.
     */
    public function getTrackingPixelUrl(): string
    {
        return route('track.open', $this->getUnsubscribeToken());
    }

    /**
     * Get HTML for the tracking pixel image tag.
     */
    public function getTrackingPixelHtml(): string
    {
        $url = $this->getTrackingPixelUrl();
        return '<img src="' . $url . '" width="1" height="1" style="display:none" alt="" />';
    }
}

