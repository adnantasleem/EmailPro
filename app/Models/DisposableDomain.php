<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class DisposableDomain extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'domain',
    ];

    /**
     * In-memory hash map cache for global disposable domains.
     */
    protected static ?array $globalDomainsMap = null;

    /**
     * In-memory hash map cache for user-specific disposable domains.
     */
    protected static array $userDomainsMap = [];

    /**
     * Get the user that owns this domain.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if a domain is disposable.
     * Uses in-memory hash map + Laravel Cache to avoid per-email database queries.
     */
    public static function isDisposable(string $email, ?int $userId = null): bool
    {
        $atPos = strrchr($email, '@');
        $domain = strtolower($atPos !== false ? substr($atPos, 1) : $email);
        if ($domain === '') {
            return false;
        }

        // Load global domains map into static memory (backed by Cache for 1 hour)
        if (self::$globalDomainsMap === null) {
            try {
                $domains = Cache::remember('disposable_domains:global', 3600, function () {
                    return self::whereNull('user_id')
                        ->pluck('domain')
                        ->map(fn($d) => strtolower($d))
                        ->all();
                });
                self::$globalDomainsMap = array_flip($domains);
            } catch (\Exception $e) {
                self::$globalDomainsMap = [];
            }
        }

        if (isset(self::$globalDomainsMap[$domain])) {
            return true;
        }

        // Check user's own blocked domains if user ID provided
        if ($userId) {
            if (!isset(self::$userDomainsMap[$userId])) {
                try {
                    $userDomains = Cache::remember("disposable_domains:user:{$userId}", 600, function () use ($userId) {
                        return self::where('user_id', $userId)
                            ->pluck('domain')
                            ->map(fn($d) => strtolower($d))
                            ->all();
                    });
                    self::$userDomainsMap[$userId] = array_flip($userDomains);
                } catch (\Exception $e) {
                    self::$userDomainsMap[$userId] = [];
                }
            }

            if (isset(self::$userDomainsMap[$userId][$domain])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear cached domain maps when domains are added/removed.
     */
    public static function clearDomainCache(?int $userId = null): void
    {
        self::$globalDomainsMap = null;
        Cache::forget('disposable_domains:global');
        if ($userId) {
            unset(self::$userDomainsMap[$userId]);
            Cache::forget("disposable_domains:user:{$userId}");
        } else {
            self::$userDomainsMap = [];
        }
    }

    /**
     * Scope to get domains for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
