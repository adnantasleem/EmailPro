<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Recipient;
use App\Models\Campaign;

$email = 'studenthousing@dmu.ac.uk';
$userId = 3;

echo "Searching for $email in user $userId's campaigns...\n";

$recipients = Recipient::whereHas('campaign', function ($q) use ($userId) {
    $q->where('user_id', $userId);
})->where('email', $email)->with('campaign')->get();

if ($recipients->isEmpty()) {
    echo "No recipients found for this email.\n";
} else {
    foreach ($recipients as $r) {
        echo "Campaign ID: {$r->campaign_id} (Name: {$r->campaign->name})\n";
        echo "  - Status: {$r->status}\n";
        echo "  - Sent At: {$r->sent_at}\n";
        echo "  - Error: {$r->error_message}\n\n";
    }
}
