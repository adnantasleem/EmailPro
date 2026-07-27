<?php

namespace App\Jobs;

use App\Models\SmtpConfig;
use App\Services\BounceProcessorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBouncesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(BounceProcessorService $processor): void
    {
        // To prevent this job from taking too long and overlapping with the next minute's run,
        // we'll stop processing after 45 seconds.
        $startTime = microtime(true);
        $maxExecutionTime = 45; // seconds

        // Get all active SMTPs that have bounce checking enabled
        $smtps = SmtpConfig::where('is_active', true)
            ->where('bounce_check_enabled', true)
            // Prioritize ones that haven't been checked in a while
            ->orderBy('last_bounce_check_at', 'asc')
            ->get();

        if ($smtps->isEmpty()) {
            return;
        }

        $totalProcessed = 0;

        foreach ($smtps as $smtp) {
            // Check if we're out of time
            if (microtime(true) - $startTime > $maxExecutionTime) {
                Log::warning("ProcessBouncesJob: Reached 45s time limit. Stopping early.");
                break;
            }

            try {
                $processed = $processor->processBounces($smtp);
                $totalProcessed += $processed;
            } catch (\Exception $e) {
                Log::error("ProcessBouncesJob: Error processing SMTP {$smtp->id}: " . $e->getMessage());
            }
        }
        
        if ($totalProcessed > 0) {
            Log::info("ProcessBouncesJob: Finished processing {$totalProcessed} total bounces.");
        }
    }
}
