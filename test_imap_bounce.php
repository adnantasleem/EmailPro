<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SmtpConfig;
use App\Services\BounceProcessorService;
use Illuminate\Support\Facades\Log;

echo "Starting IMAP Bounce Check Debug...\n";

// Get active SMTPs with bounce_check_enabled
$smtps = SmtpConfig::where('is_active', true)
    ->where('bounce_check_enabled', true)
    ->get();

if ($smtps->isEmpty()) {
    echo "No active SMTP configs found with bounce_check_enabled = true.\n";
    exit;
}

$processor = new class extends BounceProcessorService {
    // Override methods to add debug output
    public function debugProcess(SmtpConfig $smtp) {
        echo "Processing SMTP ID: {$smtp->id} ({$smtp->username})\n";
        
        if (!function_exists('imap_open')) {
            echo "ERROR: PHP IMAP extension not installed.\n";
            return;
        }

        try {
            $inbox = $this->connectToImap($smtp);
            echo "Successfully connected to IMAP mailbox.\n";
        } catch (\Exception $e) {
            echo "ERROR connecting to IMAP: " . $e->getMessage() . "\n";
            return;
        }

        // Let's look for ALL emails first to see if the inbox is empty
        $allEmails = imap_search($inbox, 'ALL');
        $allCount = $allEmails ? count($allEmails) : 0;
        echo "Total emails in inbox: {$allCount}\n";

        // Now search for UNSEEN
        $emails = imap_search($inbox, 'UNSEEN');
        $unseenCount = $emails ? count($emails) : 0;
        echo "Unseen emails: {$unseenCount}\n";

        if ($emails) {
            foreach ($emails as $emailNumber) {
                echo "\n--- Email #{$emailNumber} ---\n";
                try {
                    $headerInfo = imap_headerinfo($inbox, $emailNumber);
                    $structure = imap_fetchstructure($inbox, $emailNumber);
                    $header = imap_fetchheader($inbox, $emailNumber);
                    $body = $this->getBody($inbox, $emailNumber, $structure);
                    
                    $subject = $headerInfo->subject ?? 'No Subject';
                    $from = isset($headerInfo->from[0]) ? $headerInfo->from[0]->mailbox . '@' . $headerInfo->from[0]->host : 'Unknown';
                    
                    echo "Subject: {$subject}\n";
                    echo "From: {$from}\n";
                    
                    $isBounce = $this->isBounceEmail($headerInfo, $header, $body);
                    echo "Is Bounce? " . ($isBounce ? "YES" : "NO") . "\n";
                    
                    if ($isBounce) {
                        $bouncedEmail = $this->extractBouncedAddress($header, $body);
                        echo "Extracted Bounced Address: " . ($bouncedEmail ?: "NONE FOUND") . "\n";
                        
                        // We won't actually process it in this debug script, just mark it seen or unseen based on needs
                    }
                    
                    // Keep it unseen for now so we don't disrupt the real process
                    imap_clearflag_full($inbox, $emailNumber, "\\Seen");
                    
                } catch (\Exception $e) {
                    echo "Error reading email: " . $e->getMessage() . "\n";
                }
            }
        }
        
        imap_close($inbox);
    }
};

foreach ($smtps as $smtp) {
    echo "\n========================================\n";
    $processor->debugProcess($smtp);
}

echo "\nDone.\n";
