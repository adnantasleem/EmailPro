<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\SubjectLine;
use App\Models\BodyTemplate;

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
        return $campaign->subjectLines()
            ->select('id', 'subject', 'usage_count')
            ->orderByDesc('usage_count')
            ->get()
            ->toArray();
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
            ->map(function ($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'usage_count' => $template->usage_count,
                    'preview' => substr(strip_tags($template->html_content), 0, 100) . '...',
                ];
            })
            ->toArray();
    }
}
