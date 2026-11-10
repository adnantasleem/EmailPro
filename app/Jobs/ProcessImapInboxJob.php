<?php

namespace App\Jobs;

use App\Models\SmtpConfig;
use App\Services\ImapProcessorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessImapInboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $maxExecutionTime;

    /**
     * Create a new job instance.
     */
    public function __construct(int $maxExecutionTime = 20)
    {
        $this->maxExecutionTime = $maxExecutionTime;
    }

    /**
     * Execute the job.
     */
    public function handle(ImapProcessorService $processor): void
    {
        $startTime = microtime(true);

        // Get all active SMTPs that have bounce checking enabled, prioritizing unchecked/oldest-checked first
        $smtps = SmtpConfig::where('is_active', true)
            ->where('bounce_check_enabled', true)
            ->whereNotNull('imap_host')
            ->where('imap_host', '!=', '')
            ->orderByRaw('last_bounce_check_at IS NOT NULL, last_bounce_check_at ASC')
            ->get();

        if ($smtps->isEmpty()) {
            return;
        }

        $totalProcessed = 0;

        foreach ($smtps as $smtp) {
            if ((microtime(true) - $startTime) >= $this->maxExecutionTime) {
                break;
            }

            try {
                $processed = $processor->processInbox($smtp, 50);
                $totalProcessed += $processed;
            } catch (\Exception $e) {
                Log::error("ProcessImapInboxJob: Error processing SMTP {$smtp->id}: " . $e->getMessage());
            }
        }
        
        if ($totalProcessed > 0) {
            Log::info("ProcessImapInboxJob: Finished processing {$totalProcessed} total emails.");
        }
    }
}
