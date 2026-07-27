<?php
// Quick script to check recipient statuses for campaign 43
// Run with: php artisan tinker < check_bounce.php

$results = \Illuminate\Support\Facades\DB::select("
    SELECT 
        status, 
        COUNT(*) as total,
        GROUP_CONCAT(DISTINCT LEFT(error_message, 100) SEPARATOR ' ||| ') as sample_errors
    FROM recipients 
    WHERE campaign_id = 43
    GROUP BY status
    ORDER BY total DESC
");

echo "\n=== RECIPIENT STATUS BREAKDOWN FOR CAMPAIGN 43 ===\n\n";
foreach ($results as $row) {
    echo "Status: {$row->status} | Count: {$row->total}\n";
    if ($row->sample_errors) {
        echo "  Sample errors: {$row->sample_errors}\n";
    }
    echo "\n";
}

// Also check if any failed recipients match bounce keywords
$failedWithBounce = \Illuminate\Support\Facades\DB::select("
    SELECT email, LEFT(error_message, 150) as error_msg
    FROM recipients 
    WHERE campaign_id = 43 
      AND status = 'failed'
      AND (
          error_message LIKE '%bounce%'
          OR error_message LIKE '%rejected%'
          OR error_message LIKE '%550%'
          OR error_message LIKE '%551%'
          OR error_message LIKE '%552%'
          OR error_message LIKE '%553%'
          OR error_message LIKE '%554%'
          OR error_message LIKE '%5.1.1%'
          OR error_message LIKE '%does not exist%'
          OR error_message LIKE '%unavailable%'
          OR error_message LIKE '%undeliverable%'
          OR error_message LIKE '%user unknown%'
          OR error_message LIKE '%no such user%'
      )
    LIMIT 10
");

echo "=== FAILED RECIPIENTS THAT SHOULD BE BOUNCED ===\n\n";
if (empty($failedWithBounce)) {
    echo "None found - no 'failed' recipients have bounce-like error messages.\n";
} else {
    foreach ($failedWithBounce as $row) {
        echo "Email: {$row->email}\n  Error: {$row->error_msg}\n\n";
    }
}
