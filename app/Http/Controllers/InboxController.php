<?php

namespace App\Http\Controllers;

use App\Models\Reply;
use App\Models\SmtpConfig;
use App\Services\ImapProcessorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InboxController extends Controller
{
    /**
     * Display a listing of the replies.
     */
    public function index()
    {
        $replies = Reply::with(['campaign', 'recipient', 'smtpConfig'])
            ->where('user_id', Auth::id())
            ->orderBy('received_at', 'desc')
            ->paginate(20);

        return view('inbox.index', compact('replies'));
    }

    /**
     * Manually check IMAP inboxes for new replies and bounces.
     */
    public function sync(ImapProcessorService $processor)
    {
        $smtps = SmtpConfig::where('user_id', Auth::id())
            ->where('is_active', true)
            ->where('bounce_check_enabled', true)
            ->whereNotNull('imap_host')
            ->where('imap_host', '!=', '')
            ->orderByRaw('last_bounce_check_at IS NOT NULL, last_bounce_check_at ASC')
            ->get();

        if ($smtps->isEmpty()) {
            return redirect()->route('inbox.index')
                ->with('error', 'No active SMTP accounts found with bounce/reply detection enabled. Please enable IMAP in your SMTP settings.');
        }

        $totalProcessed = 0;
        $errors = [];
        $startTime = microtime(true);

        foreach ($smtps as $smtp) {
            // Keep manual browser sync under 25 seconds so shared hosting never returns 504 Gateway Timeout
            if ((microtime(true) - $startTime) >= 25) {
                break;
            }

            try {
                $processed = $processor->processInbox($smtp, 25, true);
                $totalProcessed += $processed;
            } catch (\Throwable $e) {
                $errors[] = "SMTP {$smtp->name}: " . $e->getMessage();
            }
        }

        if (!empty($errors)) {
            $errorMsg = implode('; ', $errors);
            return redirect()->route('inbox.index')
                ->with('warning', "Inbox sync finished with warnings ({$totalProcessed} new items processed). Errors: {$errorMsg}");
        }

        return redirect()->route('inbox.index')
            ->with('success', "Inbox checked successfully! Processed {$totalProcessed} new email(s).");
    }

    public function show(Reply $reply)
    {
        // Ensure the reply belongs to the authenticated user
        if ($reply->user_id != Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        return view('inbox.show', compact('reply'));
    }
}
