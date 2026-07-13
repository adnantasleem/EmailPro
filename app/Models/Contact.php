<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    use HasFactory;

    // Validation status constants
    const STATUS_PENDING = 'pending';
    const STATUS_VALIDATING = 'validating';
    const STATUS_VALID = 'valid';
    const STATUS_INVALID = 'invalid';

    protected $fillable = [
        'contact_list_id',
        'email',
        'name',
        'custom_fields',
        'is_active',
        'validation_status',
        'validation_result',
        'validated_at',
        'validation_error',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'validation_result' => 'array',
        'is_active' => 'boolean',
        'validated_at' => 'datetime',
    ];

    /**
     * Get the contact list this contact belongs to.
     */
    public function contactList(): BelongsTo
    {
        return $this->belongsTo(ContactList::class);
    }

    /**
     * Scope for active contacts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for valid contacts.
     */
    public function scopeValid($query)
    {
        return $query->where('validation_status', self::STATUS_VALID);
    }

    /**
     * Scope for invalid contacts.
     */
    public function scopeInvalid($query)
    {
        return $query->where('validation_status', self::STATUS_INVALID);
    }

    /**
     * Scope for contacts pending validation.
     */
    public function scopePendingValidation($query)
    {
        return $query->where('validation_status', self::STATUS_PENDING);
    }

    /**
     * Scope for contacts with a specific status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('validation_status', $status);
    }

    /**
     * Get the user through the contact list.
     */
    public function getUserIdAttribute(): ?int
    {
        return $this->contactList?->user_id;
    }

    /**
     * Mark contact as valid.
     */
    public function markAsValid(array $result): void
    {
        $this->update([
            'validation_status' => self::STATUS_VALID,
            'validation_result' => $result,
            'validated_at' => now(),
            'validation_error' => null,
        ]);
    }

    /**
     * Mark contact as invalid.
     */
    public function markAsInvalid(array $result, string $reason): void
    {
        $this->update([
            'validation_status' => self::STATUS_INVALID,
            'validation_result' => $result,
            'validated_at' => now(),
            'validation_error' => $reason,
        ]);
    }

    /**
     * Check if contact is validated.
     */
    public function isValidated(): bool
    {
        return in_array($this->validation_status, [self::STATUS_VALID, self::STATUS_INVALID]);
    }

    /**
     * Check if contact is valid.
     */
    public function isValid(): bool
    {
        return $this->validation_status === self::STATUS_VALID;
    }
}
