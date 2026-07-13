<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Unsubscribe extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email',
        'reason',
        'unsubscribed_at',
    ];

    protected $casts = [
        'unsubscribed_at' => 'datetime',
    ];

    /**
     * Get the user that owns this unsubscribe entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if an email is unsubscribed for a user.
     */
    public static function isUnsubscribed(int $userId, string $email): bool
    {
        return static::where('user_id', $userId)
            ->where('email', strtolower($email))
            ->exists();
    }

    /**
     * Add an email to unsubscribe list.
     */
    public static function addEmail(int $userId, string $email, ?string $reason = null): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId, 'email' => strtolower($email)],
            ['reason' => $reason, 'unsubscribed_at' => now()]
        );
    }

    /**
     * Remove an email from unsubscribe list.
     */
    public static function removeEmail(int $userId, string $email): bool
    {
        return static::where('user_id', $userId)
            ->where('email', strtolower($email))
            ->delete() > 0;
    }

    /**
     * Import multiple emails to unsubscribe list.
     */
    public static function importEmails(int $userId, string $emailsText): array
    {
        $lines = preg_split('/[\r\n,]+/', $emailsText);
        $imported = 0;
        $duplicates = 0;

        foreach ($lines as $line) {
            $email = strtolower(trim($line));
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $result = static::firstOrCreate(
                ['user_id' => $userId, 'email' => $email],
                ['unsubscribed_at' => now()]
            );

            if ($result->wasRecentlyCreated) {
                $imported++;
            } else {
                $duplicates++;
            }
        }

        return ['imported' => $imported, 'duplicates' => $duplicates];
    }

    /**
     * Get all unsubscribed emails for a user as array.
     */
    public static function getEmailsArray(int $userId): array
    {
        return static::where('user_id', $userId)
            ->pluck('email')
            ->toArray();
    }
}
