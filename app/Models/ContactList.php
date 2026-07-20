<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContactList extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'contacts_count',
    ];

    protected $casts = [
        'contacts_count' => 'integer',
    ];

    /**
     * Get the user that owns this list.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all contacts in this list.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * Get campaigns using this list.
     */
    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'campaign_contact_list')
            ->withTimestamps();
    }

    /**
     * Get active contacts count.
     */
    public function getActiveContactsCountAttribute(): int
    {
        return $this->contacts()->where('is_active', true)->count();
    }

    /**
     * Update the contacts count.
     */
    public function updateContactsCount(): void
    {
        $this->update(['contacts_count' => $this->contacts()->count()]);
    }

    /**
     * Import contacts from CSV text with FAST validation only.
     * 
     * Fast checks done during import (no network calls):
     * - Basic email syntax
     * - Duplicates in this list
     * - Duplicates across ALL user's contact lists
     * - Unsubscribed emails
     * - Blocklisted emails
     * - Disposable domains
     * 
     * Server-side validation (DNS, MX, SMTP) is done by background job.
     */
    public function stageImports(string $emailsText, ?int $userId = null): array
    {
        $lines = preg_split('/[\r\n]+/', $emailsText);
        $imported = 0;
        $duplicates = 0;
        $globalDuplicates = 0;
        $unsubscribed = 0;
        $blocklisted = 0;
        $invalidSyntax = 0;
        $disposable = 0;

        // Track skipped emails with their status for export
        $skippedEmails = [];
        $validEmails = [];

        // Preload lists as HASH MAPS (array_flip) for O(1) isset() lookups
        // instead of O(n) in_array() — drastically reduces memory + CPU usage
        $unsubscribedMap = [];
        $invalidMap = [];
        $allUserEmailsMap = [];
        $existingInListMap = [];
        $disposableDomainsMap = [];
        
        try {
            $disposableDomainsMap = array_flip(
                \App\Models\DisposableDomain::pluck('domain')->map(fn($d) => strtolower($d))->all()
            );
        } catch (\Exception $e) {
            // Table might not exist
        }
        
        if ($userId) {
            $unsubscribedMap = array_flip(
                Unsubscribe::where('user_id', $userId)
                    ->pluck('email')
                    ->map(fn($e) => strtolower($e))
                    ->all()
            );
            
            $invalidMap = array_flip(
                InvalidEmail::where('user_id', $userId)
                    ->pluck('email')
                    ->map(fn($e) => strtolower($e))
                    ->all()
            );
            
            // Load contacts from other lists in chunks to avoid memory exhaustion
            $allUserEmailsMap = [];
            $otherListIds = ContactList::where('user_id', $userId)
                ->where('id', '!=', $this->id)
                ->pluck('id');
            
            if ($otherListIds->isNotEmpty()) {
                Contact::whereIn('contact_list_id', $otherListIds)
                    ->select('id', 'email')
                    ->chunkById(5000, function ($contacts) use (&$allUserEmailsMap) {
                        foreach ($contacts as $contact) {
                            $allUserEmailsMap[strtolower($contact->email)] = true;
                        }
                    });
            }
        }
        
        // Pre-load existing emails in this list as hash map
        $existingInListMap = array_flip(
            $this->contacts()->pluck('email')->map(fn($e) => strtolower($e))->all()
        );
        $newlyAdded = []; // Track emails added during this import (hash map)

        $headers = null;
        $isFirstLine = true;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Parse CSV or simple format
            if (str_contains($line, ',') || str_contains($line, "\t")) {
                $columns = str_getcsv($line);
                $columns = array_map('trim', $columns);
                
                if ($isFirstLine && count($columns) > 1) {
                    $isFirstLine = false;
                    if (!filter_var($columns[0], FILTER_VALIDATE_EMAIL)) {
                        $headers = array_map('strtolower', $columns);
                        continue;
                    }
                }

                $email = strtolower($columns[0] ?? '');
                $name = !empty($columns[1]) ? $columns[1] : null;
                
                $customFields = [];
                if ($headers && count($columns) > 2) {
                    for ($i = 2; $i < count($columns); $i++) {
                        if (isset($headers[$i]) && !empty($columns[$i])) {
                            $customFields[$headers[$i]] = $columns[$i];
                        }
                    }
                } elseif (count($columns) > 2) {
                    for ($i = 2; $i < count($columns); $i++) {
                        if (!empty($columns[$i])) {
                            $customFields['field_' . ($i - 1)] = $columns[$i];
                        }
                    }
                }
            } else {
                $isFirstLine = false;
                if (preg_match('/^(.+?)\s*<(.+?)>$/', $line, $matches)) {
                    $name = trim($matches[1]);
                    $email = strtolower(trim($matches[2]));
                } else {
                    $name = null;
                    $email = strtolower($line);
                }
                $customFields = [];
            }

            // === FAST LOCAL VALIDATION CHECKS (no network calls) ===

            // 1. Basic syntax check
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalidSyntax++;
                $skippedEmails[] = ['email' => $email, 'name' => $name ?? '', 'status' => 'Invalid Syntax'];
                continue;
            }

            // 2. Unsubscribed check
            if (isset($unsubscribedMap[$email])) {
                $unsubscribed++;
                $skippedEmails[] = ['email' => $email, 'name' => $name ?? '', 'status' => 'Unsubscribed'];
                continue;
            }

            // 3. Blocklist check
            if (isset($invalidMap[$email])) {
                $blocklisted++;
                $skippedEmails[] = ['email' => $email, 'name' => $name ?? '', 'status' => 'Blocklisted'];
                continue;
            }

            // 4. Duplicate in THIS list (check both existing and newly added)
            if (isset($existingInListMap[$email]) || isset($newlyAdded[$email])) {
                $duplicates++;
                $skippedEmails[] = ['email' => $email, 'name' => $name ?? '', 'status' => 'Duplicate in this list'];
                continue;
            }

            // 5. Duplicate in other lists
            if (isset($allUserEmailsMap[$email])) {
                $globalDuplicates++;
                $skippedEmails[] = ['email' => $email, 'name' => $name ?? '', 'status' => 'Already in other list'];
                continue;
            }

            // 6. Disposable domain check
            $domain = strtolower(substr(strrchr($email, '@'), 1));
            if (isset($disposableDomainsMap[$domain])) {
                $disposable++;
                $skippedEmails[] = ['email' => $email, 'name' => $name ?? '', 'status' => 'Disposable email domain', 'custom_fields' => !empty($customFields) ? $customFields : null];
                continue;
            }

            // All fast checks passed - stage for import
            $validEmails[] = [
                'email' => $email,
                'name' => $name,
                'custom_fields' => !empty($customFields) ? $customFields : null,
                'status' => 'OK'
            ];
            
            $newlyAdded[$email] = true;
            $imported++;
        }

        return [
            'imported' => $imported,
            'duplicates' => $duplicates,
            'global_duplicates' => $globalDuplicates,
            'unsubscribed' => $unsubscribed,
            'blocklisted' => $blocklisted,
            'invalid_syntax' => $invalidSyntax,
            'disposable' => $disposable,
            'skipped_emails' => $skippedEmails,
            'valid_emails' => $validEmails,
        ];
    }

    /**
     * Save staged contacts into the database.
     */
    public function saveStagedContacts(array $validEmails): int
    {
        $imported = 0;
        $now = now()->toDateTimeString();
        $chunks = array_chunk($validEmails, 1000);

        foreach ($chunks as $chunk) {
            $insertData = [];
            foreach ($chunk as $contact) {
                $insertData[] = [
                    'contact_list_id' => $this->id,
                    'email' => $contact['email'],
                    'name' => $contact['name'] ?? null,
                    'custom_fields' => !empty($contact['custom_fields']) ? json_encode($contact['custom_fields']) : null,
                    'is_active' => 1,
                    'validation_status' => Contact::STATUS_PENDING,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            \Illuminate\Support\Facades\DB::table('contacts')->insertOrIgnore($insertData);
            $imported += count($insertData);
        }

        $this->updateContactsCount();
        return $imported;
    }
}
