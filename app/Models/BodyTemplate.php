<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BodyTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'campaign_id',
        'body_group_id',
        'name',
        'html_content',
        'plain_content',
        'usage_count',
    ];

    protected $casts = [
        'usage_count' => 'integer',
    ];

    /**
     * Get the user that owns this body template.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the campaign that owns this body template.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the group this body template belongs to.
     */
    public function bodyGroup(): BelongsTo
    {
        return $this->belongsTo(BodyGroup::class);
    }

    /**
     * Get email logs that used this body template.
     */
    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    /**
     * Increment the usage counter.
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Replace variables in HTML content.
     */
    public function getProcessedHtml(Recipient $recipient, ?string $unsubscribeLink = null): string
    {
        return $this->replaceVariables($this->html_content, $recipient, $unsubscribeLink);
    }

    /**
     * Replace variables in plain text content.
     */
    public function getProcessedPlainText(Recipient $recipient, ?string $unsubscribeLink = null): string
    {
        return $this->replaceVariables($this->plain_content ?? strip_tags($this->html_content), $recipient, $unsubscribeLink);
    }

    /**
     * Replace template variables with actual values.
     * Supports built-in variables and custom fields from recipient.
     * Handles multiple formats: {{var}}, @{{var}}, and {var}
     */
    protected function replaceVariables(string $content, Recipient $recipient, ?string $unsubscribeLink = null): string
    {
        // Extract first name from full name
        $name = $recipient->name ?? '';
        $firstName = $name;
        if ($name && str_contains($name, ' ')) {
            $firstName = explode(' ', $name)[0];
        }

        // Unsubscribe URL
        $unsubscribeUrl = $unsubscribeLink ?? '#';
        // Full HTML link for standalone use
        $unsubscribeLinkHtml = '<a href="' . htmlspecialchars($unsubscribeUrl) . '" style="color: #6366f1; text-decoration: underline;">Unsubscribe</a>';

        $replacements = [
            'name' => $name,
            'first_name' => $firstName,
            'email' => $recipient->email,
            'unsubscribe_link' => $unsubscribeUrl, // Just the URL for use in href=""
            'unsubscribe_url' => $unsubscribeUrl, // Alias
            'unsubscribe_text' => $unsubscribeLinkHtml, // Full HTML link for standalone use
            'date' => now()->format('F j, Y'),
            'year' => now()->format('Y'),
        ];

        // Add custom fields from recipient
        if (!empty($recipient->custom_fields) && is_array($recipient->custom_fields)) {
            foreach ($recipient->custom_fields as $key => $value) {
                $replacements[strtolower($key)] = $value ?? '';
            }
        }

        // Replace all variable formats: @{{ var }}, {{ var }}, { var } (with optional spaces)
        foreach ($replacements as $key => $value) {
            // Handle @{{ var }} format (used in Blade templates to escape Blade syntax)
            $content = preg_replace('/@\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/i', $value, $content);
            // Handle {{ var }} format
            $content = preg_replace('/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/i', $value, $content);
            // Handle { var } format (single braces)
            $content = preg_replace('/\{\s*' . preg_quote($key, '/') . '\s*\}/i', $value, $content);
        }

        return $content;
    }
}
