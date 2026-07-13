<?php

namespace App\Http\Controllers;

use App\Models\Recipient;
use App\Models\Unsubscribe;
use Illuminate\Http\Request;

class PublicUnsubscribeController extends Controller
{
    /**
     * Show the unsubscribe confirmation page.
     */
    public function show(string $token)
    {
        $recipient = Recipient::where('unsubscribe_token', $token)->first();

        if (!$recipient) {
            return view('public.unsubscribe-invalid');
        }

        return view('public.unsubscribe', [
            'email' => $recipient->email,
            'token' => $token,
        ]);
    }

    /**
     * Process the unsubscribe request.
     */
    public function process(Request $request, string $token)
    {
        $recipient = Recipient::where('unsubscribe_token', $token)->first();

        if (!$recipient) {
            return view('public.unsubscribe-invalid');
        }

        // Get the campaign owner's user_id
        $userId = $recipient->campaign->user_id;

        // Add to global unsubscribe list
        Unsubscribe::addEmail($userId, $recipient->email, 'Unsubscribed via email link');

        // Mark recipient as unsubscribed
        $recipient->update(['status' => 'unsubscribed']);

        return view('public.unsubscribe-success', [
            'email' => $recipient->email,
        ]);
    }
}
