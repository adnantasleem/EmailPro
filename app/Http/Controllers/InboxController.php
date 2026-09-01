<?php

namespace App\Http\Controllers;

use App\Models\Reply;
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
     * Display the specified reply.
     */
    public function show(Reply $reply)
    {
        // Ensure the reply belongs to the authenticated user
        if ($reply->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        return view('inbox.show', compact('reply'));
    }
}
