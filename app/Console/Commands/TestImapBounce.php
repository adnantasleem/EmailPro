<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SmtpConfig;
use App\Services\ImapProcessorService;
use Illuminate\Support\Facades\Log;

class TestImapBounce extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:process-inbox {smtp_id?} {--test : Only test connection without processing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process or test IMAP mailbox for replies and bounces for a specific SMTP or all active ones.';

    /**
     * Execute the console command.
     */
    public function handle(ImapProcessorService $processor)
    {
        $smtpId = $this->argument('smtp_id');

        $query = SmtpConfig::where('is_active', true)
            ->where('bounce_check_enabled', true);

        if ($smtpId) {
            $query->where('id', $smtpId);
        }

        $smtps = $query->get();

        if ($smtps->isEmpty()) {
            $this->error("No active SMTP configurations found with IMAP bounce/reply checking enabled.");
            $this->info("Tip: Ensure 'is_active' and 'bounce_check_enabled' are checked on your SMTP account.");
            return 1;
        }

        $isTestOnly = $this->option('test');

        foreach ($smtps as $smtp) {
            $this->info("==========================================");
            $this->info("Testing SMTP ID: {$smtp->id} ({$smtp->name} - {$smtp->username})");
            $this->line("Host: {$smtp->imap_host}:{$smtp->imap_port} (Encryption: {$smtp->imap_encryption})");
            $this->line("Folder: " . ($smtp->imap_folder ?: 'INBOX'));

            try {
                $this->line("Connecting to IMAP...");
                $startTime = microtime(true);
                $smtp->testImapConnection();
                $elapsed = round(microtime(true) - $startTime, 2);
                $this->info("✅ Successfully connected to IMAP mailbox in {$elapsed}s!");
            } catch (\Throwable $e) {
                $this->error("❌ Failed to connect to IMAP: " . $e->getMessage());
                $this->warn("Common causes on shared hosting:");
                $this->warn(" 1. Port {$smtp->imap_port} blocked by shared hosting firewall (CSF/iptables).");
                $this->warn(" 2. Hostname issue: try 'localhost' or '127.0.0.1' if mail server is on the same cPanel server.");
                $this->warn(" 3. Incorrect username or password.");
                continue;
            }

            if ($isTestOnly) {
                $this->info("Test mode enabled (--test): skipping message reading.");
                continue;
            }

            $this->line("Processing inbox for bounces and replies...");
            try {
                $processed = $processor->processInbox($smtp);
                $this->info("✅ Processed {$processed} email(s) for SMTP ID {$smtp->id}.");
            } catch (\Throwable $e) {
                $this->error("❌ Error while processing inbox: " . $e->getMessage());
            }
        }

        $this->info("==========================================");
        $this->info("Done.");
        return 0;
    }
}
