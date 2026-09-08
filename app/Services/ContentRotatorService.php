<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\SubjectLine;
use App\Models\BodyTemplate;
use Illuminate\Support\Facades\DB;

class ContentRotatorService
{
    /**
     * Get a random subject line for a campaign.
     */
    public function getRandomSubject(Campaign $campaign): ?SubjectLine
    {
        return $campaign->subjectLines()->inRandomOrder()->first();
    }

    /**
     * Get a random body template for a campaign.
     */
    public function getRandomBody(Campaign $campaign): ?BodyTemplate
    {
        return $campaign->bodyTemplates()->inRandomOrder()->first();
    }

    /**
     * Get random content (subject + body) for a campaign.
     */
    public function getRandomContent(Campaign $campaign): array
    {
        return [
            'subject' => $this->getRandomSubject($campaign),
            'body' => $this->getRandomBody($campaign),
        ];
    }

    /**
     * Increment usage counters for used content.
     */
    public function trackUsage(SubjectLine $subjectLine, BodyTemplate $bodyTemplate): void
    {
        $subjectLine->incrementUsage();
        $bodyTemplate->incrementUsage();
    }

    /**
     * Get subject line usage statistics for a campaign.
     */
    public function getSubjectStats(Campaign $campaign): array
    {
        $subjects = $campaign->subjectLines()
            ->select('id', 'subject', 'usage_count')
            ->orderByDesc('usage_count')
            ->get();

        return $subjects->map(function ($subject) use ($campaign) {
            // Get stats directly from email_logs joined with recipients
            $stats = DB::table('email_logs')
                ->join('recipients', 'email_logs.recipient_id', '=', 'recipients.id')
                ->where('email_logs.campaign_id', $campaign->id)
                ->where('email_logs.subject_line_id', $subject->id)
                ->selectRaw('COUNT(*) as sent_count')
                ->selectRaw('SUM(CASE WHEN recipients.opened_at IS NOT NULL THEN 1 ELSE 0 END) as open_count')
                ->selectRaw('SUM(CASE WHEN recipients.status = "replied" THEN 1 ELSE 0 END) as reply_count')
                ->first();

            $sent = $stats->sent_count ?? 0;
            $opens = $stats->open_count ?? 0;
            $replies = $stats->reply_count ?? 0;

            return [
                'id' => $subject->id,
                'subject' => $subject->subject,
                'usage_count' => $subject->usage_count,
                'sent_count' => $sent,
                'open_count' => $opens,
                'open_rate' => $sent > 0 ? round(($opens / $sent) * 100, 1) : 0,
                'reply_count' => $replies,
                'reply_rate' => $sent > 0 ? round(($replies / $sent) * 100, 1) : 0,
            ];
        })->toArray();
    }

    /**
     * Get body template usage statistics for a campaign.
     */
    public function getBodyStats(Campaign $campaign): array
    {
        return $campaign->bodyTemplates()
            ->select('id', 'name', 'usage_count', 'html_content')
            ->orderByDesc('usage_count')
            ->get()
            ->map(function ($template) use ($campaign) {
                // Get stats directly from email_logs joined with recipients
                $stats = DB::table('email_logs')
                    ->join('recipients', 'email_logs.recipient_id', '=', 'recipients.id')
                    ->where('email_logs.campaign_id', $campaign->id)
                    ->where('email_logs.body_template_id', $template->id)
                    ->selectRaw('COUNT(*) as sent_count')
                    ->selectRaw('SUM(CASE WHEN recipients.opened_at IS NOT NULL THEN 1 ELSE 0 END) as open_count')
                    ->selectRaw('SUM(CASE WHEN recipients.status = "replied" THEN 1 ELSE 0 END) as reply_count')
                    ->first();

                $sent = $stats->sent_count ?? 0;
                $opens = $stats->open_count ?? 0;
                $replies = $stats->reply_count ?? 0;

                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'preview' => substr(strip_tags($template->html_content), 0, 100) . '...',
                    'usage_count' => $template->usage_count,
                    'sent_count' => $sent,
                    'open_count' => $opens,
                    'open_rate' => $sent > 0 ? round(($opens / $sent) * 100, 1) : 0,
                    'reply_count' => $replies,
                    'reply_rate' => $sent > 0 ? round(($replies / $sent) * 100, 1) : 0,
                ];
            })
            ->toArray();
    }
}
