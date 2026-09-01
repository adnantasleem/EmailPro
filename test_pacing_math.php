<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SmtpConfig;
use Illuminate\Support\Facades\Cache;

function testPacing($strategy, $daily, $minHr, $maxHr) {
    echo "--- Testing Strategy: {$strategy} ---\n";
    $smtp = new SmtpConfig();
    $smtp->id = 999;
    $smtp->pacing_strategy = $strategy;
    $smtp->current_daily_limit = $daily;
    $smtp->daily_limit = $daily;
    $smtp->min_emails_per_hour = $minHr;
    $smtp->max_emails_per_hour = $maxHr;
    $smtp->sent_last_hour = 0;
    
    // Simulate canSend() logic manually to test math
    $activeHours = 24;
    
    if ($strategy === 'per_day') {
        $hourlyAvg = $daily / $activeHours;
        $min = max(1, (int) floor($hourlyAvg));
        $max = max(1, (int) ceil($hourlyAvg));
    } else {
        $min = $minHr ?? ($daily ? max(1, (int) floor($daily / 24)) : 20);
        $max = $maxHr ?? ($daily ? max(1, (int) ceil($daily / 24)) : 20);
    }
    
    $hourlyLimit = max(1, rand($min, $max));
    $minSecondsBetweenEmails = (int) floor(3600 / $hourlyLimit);
    
    echo "Daily Limit: {$daily}\n";
    echo "Calculated Hourly Limit: {$hourlyLimit}\n";
    echo "Seconds delay between each email: {$minSecondsBetweenEmails} seconds\n";
    echo "Total emails that can fit in one hour: " . floor(3600 / $minSecondsBetweenEmails) . "\n\n";
}

testPacing('per_day', 160, null, null);
testPacing('per_hour', 160, 10, 10);
testPacing('per_hour', 160, 160, 160);

