<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisposableDomain extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'domain',
    ];

    /**
     * Get the user that owns this domain.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if a domain is disposable.
     * Checks both global domains (from seeder) and user's own blocked domains.
     */
    public static function isDisposable(string $email, ?int $userId = null): bool
    {
        $domain = strtolower(substr(strrchr($email, '@'), 1));
        
        // Check global domains (user_id is null - from seeder)
        $isGlobalBlocked = self::where('domain', $domain)
            ->whereNull('user_id')
            ->exists();
        
        if ($isGlobalBlocked) {
            return true;
        }
        
        // Check user's own blocked domains if user ID provided
        if ($userId) {
            return self::where('domain', $domain)
                ->where('user_id', $userId)
                ->exists();
        }
        
        return false;
    }

    /**
     * Scope to get domains for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
