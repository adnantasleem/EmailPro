<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SmtpConfig;
use App\Services\BounceProcessorService;
use Illuminate\Support\Facades\Log;

class TestImapBounce extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test-bounce {smtp_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test IMAP bounce monitoring for a specific SMTP or all active ones.';

    /**
     * Execute the console command.
     */
    public function handle(BounceProcessorService $processor)
    {
        $smtpId = $this->argument('smtp_id');

        $query = SmtpConfig::where('is_active', true)
            ->where('bounce_check_enabled', true);

        if ($smtpId) {
            $query->where('id', $smtpId);
        }

        $smtps = $query->get();

        if ($smtps->isEmpty()) {
            $this->error("No active SMTP configurations found with IMAP bounce checking enabled.");
            return;
        }

        if (!function_exists('imap_open')) {
            $this->error("PHP IMAP extension is NOT installed on this server. Bounce checking cannot work.");
            return;
        }

        foreach ($smtps as $smtp) {
            $this->info("==========================================");
            $this->info("Testing SMTP ID: {$smtp->id} ({$smtp->username})");
            
            try {
                // Use reflection to call protected connectToImap method
                $reflection = new \ReflectionClass($processor);
                $method = $reflection->getMethod('connectToImap');
                $method->setAccessible(true);
                
                $inbox = $method->invokeArgs($processor, [$smtp]);
                $this->info("✅ Successfully connected to IMAP mailbox.");
            } catch (\Exception $e) {
                $this->error("❌ Failed to connect to IMAP: " . $e->getMessage());
                continue;
            }

            // Check total emails
            $allEmails = imap_search($inbox, 'ALL');
            $allCount = $allEmails ? count($allEmails) : 0;
            $this->line("Total emails in folder '{$smtp->imap_folder}': {$allCount}");

            // Check UNSEEN emails
            $unseenEmails = imap_search($inbox, 'UNSEEN');
            $unseenCount = $unseenEmails ? count($unseenEmails) : 0;
            $this->line("Unread (UNSEEN) emails: {$unseenCount}");

            if ($unseenCount === 0) {
                $this->warn("No unread emails found. The system only processes UNREAD emails. If you already opened the bounce email in your webmail, it will be ignored.");
            } else {
                $this->info("Scanning unread emails for bounces...");
                $bouncesFound = 0;

                $isBounceMethod = $reflection->getMethod('isBounceEmail');
                $isBounceMethod->setAccessible(true);

                $extractMethod = $reflection->getMethod('extractBouncedAddress');
                $extractMethod->setAccessible(true);

                $getBodyMethod = $reflection->getMethod('getBody');
                $getBodyMethod->setAccessible(true);

                foreach ($unseenEmails as $emailNumber) {
                    try {
                        $headerInfo = imap_headerinfo($inbox, $emailNumber);
                        $header = imap_fetchheader($inbox, $emailNumber);
                        $structure = imap_fetchstructure($inbox, $emailNumber);
                        $body = $getBodyMethod->invokeArgs($processor, [$inbox, $emailNumber, $structure, ""]);

                        $subject = $headerInfo->subject ?? 'No Subject';
                        
                        $isBounce = $isBounceMethod->invokeArgs($processor, [$headerInfo, $header, $body]);
                        
                        if ($isBounce) {
                            $bouncesFound++;
                            $bouncedEmail = $extractMethod->invokeArgs($processor, [$header, $body]);
                            $this->line("🚨 BOUNCE DETECTED! Subject: {$subject}");
                            $this->line("   -> Extracted Bounced Address: " . ($bouncedEmail ?: 'Could not extract address'));
                        }

                    } catch (\Exception $e) {
                        $this->error("Error reading email #{$emailNumber}: " . $e->getMessage());
                    }
                }
                
                $this->info("Finished scanning. Found {$bouncesFound} bounce(s) among {$unseenCount} unread emails.");
            }

            imap_close($inbox);
        }
    }
}
