<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvalidEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email',
        'reason',
        'detected_at',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
    ];

    /**
     * Get the user that owns this invalid email.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Add an email to the invalid list.
     */
    public static function addEmail(int $userId, string $email, string $reason = ''): ?self
    {
        $email = strtolower(trim($email));

        // Check if already exists
        $existing = self::where('user_id', $userId)
            ->where('email', $email)
            ->first();

        if ($existing) {
            return $existing;
        }

        return self::create([
            'user_id' => $userId,
            'email' => $email,
            'reason' => $reason,
            'detected_at' => now(),
        ]);
    }

    /**
     * Check if an email is in the invalid list.
     */
    public static function isInvalid(int $userId, string $email): bool
    {
        return self::where('user_id', $userId)
            ->where('email', strtolower(trim($email)))
            ->exists();
    }

    /**
     * Get all invalid emails as array for filtering.
     */
    public static function getEmailsArray(int $userId): array
    {
        return self::where('user_id', $userId)
            ->pluck('email')
            ->toArray();
    }
}
