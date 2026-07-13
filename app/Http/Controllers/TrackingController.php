<?php

namespace App\Http\Controllers;

use App\Models\Recipient;
use Illuminate\Http\Response;

class TrackingController extends Controller
{
    /**
     * 1x1 transparent GIF pixel (base64 decoded).
     */
    private const TRACKING_PIXEL = "\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00\x21\xf9\x04\x01\x00\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3b";

    /**
     * Track email open and return tracking pixel.
     */
    public function trackOpen(string $token)
    {
        // Find recipient by token
        $recipient = Recipient::where('unsubscribe_token', $token)->first();

        if ($recipient) {
            // Record the open
            $recipient->increment('open_count');
            
            // Set first open time if not already set
            if (!$recipient->opened_at) {
                $recipient->update(['opened_at' => now()]);
            }
        }

        // Return 1x1 transparent GIF
        return response(self::TRACKING_PIXEL, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => strlen(self::TRACKING_PIXEL),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
