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
            $query->where('status', $request->status);
        }

        // Search by email
        if ($request->has('search') && !empty($request->search)) {
            $query->where('email', 'like', '%' . $request->search . '%');
        }

        $recipients = $query->latest()->paginate(50);

        $statusCounts = [
            'all' => $campaign->recipients()->count(),
            'pending' => $campaign->recipients()->status('pending')->count(),
            'valid' => $campaign->recipients()->status('valid')->count(),
            'invalid' => $campaign->recipients()->whereIn('status', ['invalid', 'disposable'])->count(),
            'sent' => $campaign->recipients()->status('sent')->count(),
            'failed' => $campaign->recipients()->status('failed')->count(),
        ];

        return view('recipients.index', compact('campaign', 'recipients', 'statusCounts'));
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
            $query->where('status', $request->status);
        }

        $filename = "campaign_{$campaign->id}_recipients.csv";

        return response()->streamDownload(function () use ($query) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Email', 'Name', 'Status', 'Sent At', 'Error']);

            $query->chunk(1000, function ($recipients) use ($file) {
                foreach ($recipients as $recipient) {
                    fputcsv($file, [
                        $recipient->email,
                        $recipient->name ?? '',
                        $recipient->status,
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
