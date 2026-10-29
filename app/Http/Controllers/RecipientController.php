<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Recipient;
use Illuminate\Http\Request;

class RecipientController extends Controller
{
    /**
     * Display recipients for a campaign.
     */
    public function index(Campaign $campaign, Request $request)
    {
        $query = $campaign->recipients();

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            if ($request->status === 'bounced') {
                $query->bounced();
            } elseif ($request->status === 'failed') {
                $query->failedNonBounce();
            } else {
                $query->where('status', $request->status);
            }
        }

        // Filter by SMTP
        if ($request->filled('smtp_id') && $request->smtp_id !== 'all') {
            $smtpId = (int) $request->smtp_id;
            $query->where(function ($q) use ($smtpId) {
                $q->where('smtp_config_id', $smtpId)
                  ->orWhereHas('emailLogs', fn($el) => $el->where('smtp_config_id', $smtpId));
            });
        }

        // Search by email
        if ($request->has('search') && !empty($request->search)) {
            $query->where('email', 'like', '%' . $request->search . '%');
        }

        $recipients = $query->with(['smtpConfig', 'latestEmailLog.smtpConfig'])->latest()->paginate(50)->withQueryString();

        $statusCounts = [
            'all' => $campaign->recipients()->count(),
            'pending' => $campaign->recipients()->status('pending')->count(),
            'valid' => $campaign->recipients()->status('valid')->count(),
            'invalid' => $campaign->recipients()->whereIn('status', ['invalid', 'disposable'])->count(),
            'sent' => $campaign->recipients()->status('sent')->count(),
            'failed' => $campaign->recipients()->failedNonBounce()->count(),
            'bounced' => $campaign->recipients()->bounced()->count(),
        ];

        // Fetch SMTP configs used in this campaign for filtering
        $campaignSmtps = \App\Models\SmtpConfig::where('user_id', auth()->id())
            ->where(function ($q) use ($campaign) {
                $q->whereHas('campaigns', fn($c) => $c->where('campaigns.id', $campaign->id))
                  ->orWhereHas('emailLogs', fn($el) => $el->where('campaign_id', $campaign->id))
                  ->orWhereIn('id', $campaign->recipients()->whereNotNull('smtp_config_id')->select('smtp_config_id'));
            })
            ->orderBy('name')
            ->get();

        return view('recipients.index', compact('campaign', 'recipients', 'statusCounts', 'campaignSmtps'));
    }

    /**
     * Import recipients via CSV upload.
     */
    public function import(Request $request, Campaign $campaign)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('file');
        $content = file_get_contents($file->getRealPath());

        $imported = $this->importFromText($campaign, $content);

        return redirect()->route('recipients.index', $campaign)
            ->with('success', "Successfully imported {$imported} recipients.");
    }

    /**
     * Import recipients via bulk text input.
     */
    public function bulkImport(Request $request, Campaign $campaign)
    {
        $request->validate([
            'emails' => 'required|string',
        ]);

        $imported = $this->importFromText($campaign, $request->emails);

        return redirect()->route('recipients.index', $campaign)
            ->with('success', "Successfully imported {$imported} recipients.");
    }

    /**
     * Delete a recipient.
     */
    public function destroy(Campaign $campaign, Recipient $recipient)
    {
        if ($recipient->campaign_id !== $campaign->id) {
            abort(404);
        }

        $recipient->delete();

        return redirect()->route('recipients.index', $campaign)
            ->with('success', 'Recipient deleted successfully.');
    }

    /**
     * Delete all recipients with a specific status.
     */
    public function bulkDelete(Request $request, Campaign $campaign)
    {
        $request->validate([
            'status' => 'required|string|in:invalid,disposable,failed,pending',
        ]);

        $deleted = $campaign->recipients()
            ->where('status', $request->status)
            ->delete();

        return redirect()->route('recipients.index', $campaign)
            ->with('success', "Deleted {$deleted} recipients with status '{$request->status}'.");
    }

    /**
     * Export recipients to CSV.
     */
    public function export(Campaign $campaign, Request $request)
    {
        $query = $campaign->recipients();

        if ($request->has('status') && $request->status !== 'all') {
            if ($request->status === 'bounced') {
                $query->bounced();
            } elseif ($request->status === 'failed') {
                $query->failedNonBounce();
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('smtp_id') && $request->smtp_id !== 'all') {
            $smtpId = (int) $request->smtp_id;
            $query->where(function ($q) use ($smtpId) {
                $q->where('smtp_config_id', $smtpId)
                  ->orWhereHas('emailLogs', fn($el) => $el->where('smtp_config_id', $smtpId));
            });
        }

        $query->orderBy('id');

        $filename = "campaign_{$campaign->id}_recipients.csv";

        return response()->streamDownload(function () use ($query) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Email', 'Name', 'Status', 'SMTP Account', 'SMTP From Email', 'Sent At', 'Error']);

            $query->with(['smtpConfig', 'latestEmailLog.smtpConfig'])->chunk(1000, function ($recipients) use ($file) {
                foreach ($recipients as $recipient) {
                    $status = $recipient->status;
                    
                    if ($status === \App\Models\Recipient::STATUS_SENT) {
                        $status = 'delivered';
                    } elseif ($status === \App\Models\Recipient::STATUS_BOUNCED) {
                        $status = 'bounced';
                    } elseif ($status === \App\Models\Recipient::STATUS_FAILED) {
                        if (\App\Models\Recipient::isBounceError($recipient->error_message ?? '')) {
                            $status = 'bounced';
                        } else {
                            $status = 'failed';
                        }
                    }

                    $smtp = $recipient->smtp_account;

                    fputcsv($file, [
                        $recipient->email,
                        $recipient->name ?? '',
                        $status,
                        $smtp ? $smtp->name : 'N/A',
                        $smtp ? $smtp->from_email : 'N/A',
                        $recipient->sent_at?->format('Y-m-d H:i:s') ?? '',
                        $recipient->error_message ?? '',
                    ]);
                }
            });

            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Import recipients from text content.
     */
    protected function importFromText(Campaign $campaign, string $content): int
    {
        // Parse CSV or plain text
        $lines = preg_split('/[\r\n]+/', $content);
        $imported = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Try to parse as CSV
            $parts = str_getcsv($line);
            $email = null;
            $name = null;

            if (count($parts) >= 2) {
                // Assume first column is email or name
                if (filter_var($parts[0], FILTER_VALIDATE_EMAIL)) {
                    $email = $parts[0];
                    $name = $parts[1] ?? null;
                } elseif (filter_var($parts[1], FILTER_VALIDATE_EMAIL)) {
                    $name = $parts[0];
                    $email = $parts[1];
                }
            } elseif (count($parts) === 1) {
                // Single column - check for "Name <email>" format
                if (preg_match('/^(.+?)\s*<(.+?)>$/', $parts[0], $matches)) {
                    $name = trim($matches[1]);
                    $email = trim($matches[2]);
                } else {
                    $email = $parts[0];
                }
            }

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            // Check for duplicates
            if ($campaign->recipients()->where('email', strtolower($email))->exists()) {
                continue;
            }

            $campaign->recipients()->create([
                'email' => strtolower($email),
                'name' => $name,
                'status' => Recipient::STATUS_PENDING,
            ]);

            $imported++;
        }

        return $imported;
    }
}
