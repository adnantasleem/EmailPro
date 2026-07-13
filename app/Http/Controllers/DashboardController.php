<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\SmtpConfig;
use App\Models\EmailLog;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function index()
    {
        $userId = auth()->id();

        $stats = [
            'total_campaigns' => Campaign::where('user_id', $userId)->count(),
            'active_campaigns' => Campaign::where('user_id', $userId)->whereIn('status', ['validating', 'sending'])->count(),
            'completed_campaigns' => Campaign::where('user_id', $userId)->status('completed')->count(),
            'total_smtp' => SmtpConfig::where('user_id', $userId)->count(),
            'active_smtp' => SmtpConfig::where('user_id', $userId)->active()->count(),
            'emails_sent_today' => EmailLog::whereHas('campaign', fn($q) => $q->where('user_id', $userId))
                ->whereDate('sent_at', today())->count(),
            'emails_failed_today' => EmailLog::whereHas('campaign', fn($q) => $q->where('user_id', $userId))
                ->whereDate('sent_at', today())->status('failed')->count(),
        ];

        $recentCampaigns = Campaign::where('user_id', $userId)
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($campaign) {
                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'status' => $campaign->status,
                    'stats' => $campaign->stats,
                    'created_at' => $campaign->created_at->diffForHumans(),
                ];
            });

        $smtpStatus = SmtpConfig::where('user_id', $userId)->active()->limit(5)->get()->map(function ($smtp) {
            return [
                'id' => $smtp->id,
                'name' => $smtp->name,
                'sent_today' => $smtp->sent_today,
                'daily_limit' => $smtp->daily_limit,
                'remaining' => $smtp->remaining_today,
            ];
        });

        // Get paused SMTPs with auto-resume countdown
        $pausedSmtps = SmtpConfig::where('user_id', $userId)
            ->where('auto_paused', true)
            ->get()
            ->map(function ($smtp) {
                $resumeAt = $smtp->paused_at ? $smtp->paused_at->addHours(24) : null;
                return [
                    'id' => $smtp->id,
                    'name' => $smtp->name,
                    'pause_reason' => $smtp->pause_reason,
                    'paused_at' => $smtp->paused_at?->format('M d, H:i'),
                    'resume_at' => $resumeAt?->format('M d, H:i'),
                    'resume_at_iso' => $resumeAt?->toIso8601String(),
                    'is_past' => $resumeAt ? $resumeAt->isPast() : false,
                ];
            });

        // Get SMTPs that exhausted daily quota
        $exhaustedSmtps = SmtpConfig::where('user_id', $userId)
            ->where('is_active', true)
            ->where('auto_paused', false)
            ->get()
            ->filter(function ($smtp) {
                $smtp->resetDailyCounterIfNeeded();
                return $smtp->sent_today >= $smtp->getEffectiveLimit();
            })
            ->map(function ($smtp) {
                return [
                    'id' => $smtp->id,
                    'name' => $smtp->name,
                    'sent_today' => $smtp->sent_today,
                    'daily_limit' => $smtp->getEffectiveLimit(),
                ];
            });

        // Get active/sending campaigns with progress
        $activeCampaigns = Campaign::where('user_id', $userId)
            ->whereIn('status', ['sending', 'validating'])
            ->get()
            ->map(function ($campaign) {
                $statsData = $campaign->stats;
                $total = $statsData['total'] ?? 0;
                $sent = $statsData['sent'] ?? 0;
                $failed = $statsData['failed'] ?? 0;
                $progress = $total > 0 ? round((($sent + $failed) / $total) * 100, 1) : 0;
                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'status' => $campaign->status,
                    'total' => $total,
                    'sent' => $sent,
                    'failed' => $failed,
                    'progress' => $progress,
                    'started_at' => $campaign->started_at?->format('M d, H:i'),
                ];
            });

        return view('dashboard', compact('stats', 'recentCampaigns', 'smtpStatus', 'pausedSmtps', 'exhaustedSmtps', 'activeCampaigns'));
    }
}
