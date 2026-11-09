<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\SubjectLine;
use App\Models\BodyTemplate;
use Illuminate\Support\Facades\DB;

class ContentRotatorService
{
    /**
     * Get a random subject line for a campaign (uses eager-loaded collection if available).
     */
    public function getRandomSubject(Campaign $campaign): ?SubjectLine
    {
        if ($campaign->relationLoaded('subjectLines')) {
            return $campaign->subjectLines->isNotEmpty() ? $campaign->subjectLines->random() : null;
        }
        return $campaign->subjectLines()->inRandomOrder()->first();
    }

    /**
     * Get a random body template for a campaign (uses eager-loaded collection if available).
     */
    public function getRandomBody(Campaign $campaign): ?BodyTemplate
    {
        if ($campaign->relationLoaded('bodyTemplates')) {
            return $campaign->bodyTemplates->isNotEmpty() ? $campaign->bodyTemplates->random() : null;
        }
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
     * Get subject line usage statistics for a campaign (single grouped query).
     */
    public function getSubjectStats(Campaign $campaign): array
    {
        $subjects = $campaign->subjectLines()
            ->select('id', 'subject', 'usage_count')
            ->orderByDesc('usage_count')
            ->get();

        if ($subjects->isEmpty()) {
            return [];
        }

        $statsBySubject = DB::table('email_logs')
            ->join('recipients', 'email_logs.recipient_id', '=', 'recipients.id')
            ->where('email_logs.campaign_id', $campaign->id)
            ->whereIn('email_logs.subject_line_id', $subjects->pluck('id'))
            ->groupBy('email_logs.subject_line_id')
            ->select('email_logs.subject_line_id')
            ->selectRaw('COUNT(*) as sent_count')
            ->selectRaw('SUM(CASE WHEN recipients.opened_at IS NOT NULL THEN 1 ELSE 0 END) as open_count')
            ->selectRaw("SUM(CASE WHEN recipients.status = 'replied' THEN 1 ELSE 0 END) as reply_count")
            ->get()
            ->keyBy('subject_line_id');

        return $subjects->map(function ($subject) use ($statsBySubject) {
            $stats = $statsBySubject->get($subject->id);
            $sent = (int) ($stats->sent_count ?? 0);
            $opens = (int) ($stats->open_count ?? 0);
            $replies = (int) ($stats->reply_count ?? 0);

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
     * Get body template usage statistics for a campaign (single grouped query).
     */
    public function getBodyStats(Campaign $campaign): array
    {
        $templates = $campaign->bodyTemplates()
            ->select('id', 'name', 'usage_count', 'html_content')
            ->orderByDesc('usage_count')
            ->get();

        if ($templates->isEmpty()) {
            return [];
        }

        $statsByTemplate = DB::table('email_logs')
            ->join('recipients', 'email_logs.recipient_id', '=', 'recipients.id')
            ->where('email_logs.campaign_id', $campaign->id)
            ->whereIn('email_logs.body_template_id', $templates->pluck('id'))
            ->groupBy('email_logs.body_template_id')
            ->select('email_logs.body_template_id')
            ->selectRaw('COUNT(*) as sent_count')
            ->selectRaw('SUM(CASE WHEN recipients.opened_at IS NOT NULL THEN 1 ELSE 0 END) as open_count')
            ->selectRaw("SUM(CASE WHEN recipients.status = 'replied' THEN 1 ELSE 0 END) as reply_count")
            ->get()
            ->keyBy('body_template_id');

        return $templates->map(function ($template) use ($statsByTemplate) {
            $stats = $statsByTemplate->get($template->id);
            $sent = (int) ($stats->sent_count ?? 0);
            $opens = (int) ($stats->open_count ?? 0);
            $replies = (int) ($stats->reply_count ?? 0);

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
        })->toArray();
    }
}
