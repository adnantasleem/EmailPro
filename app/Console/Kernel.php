<?php

namespace App\Console;

use App\Jobs\ProcessImapInboxJob;
use App\Jobs\SendEmailsJob;
use App\Jobs\ValidateContactsJob;
use App\Jobs\ValidateEmailsJob;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\SmtpConfig;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * Designed for Shared Hosting:
     * - All tasks run synchronously inside `schedule:run` with strict dynamic time budgets
     *   so no background `queue:work` daemon or Supervisor process is required.
     * - Total execution time across all tasks in a single minute is capped below 52 seconds.
     * - Short `withoutOverlapping(3)` locks ensure automatic recovery within minutes if
     *   a process is ever terminated by shared hosting LVE/CPU limits.
     */
    protected function schedule(Schedule $schedule): void
    {
        $cronStart = defined('LARAVEL_START') ? (float) LARAVEL_START : microtime(true);

        // 1. Send emails every minute (up to 25s budget; exits in <10ms if no campaign is sending)
        $schedule->call(function () use ($cronStart) {
            $elapsed = microtime(true) - $cronStart;
            $budget = (int) min(25, max(5, floor(50 - $elapsed)));
            SendEmailsJob::dispatchSync($budget);
        })
            ->everyMinute()
            ->name('send-emails')
            ->withoutOverlapping(2);

        // 2. Validate pending contacts & campaign recipients every minute (up to 18s global budget)
        // Uses the indexed `has_pending_contacts` flag so it takes <1ms when idle.
        $schedule->call(function () use ($cronStart) {
            $elapsed = microtime(true) - $cronStart;
            $globalBudget = (int) min(18, max(0, floor(48 - $elapsed)));
            if ($globalBudget < 3) {
                return;
            }

            $validationStart = microtime(true);

            // Query contact lists with pending contacts using index flag (instant check)
            $listsWithPending = ContactList::where('has_pending_contacts', true)
                ->orderBy('updated_at', 'asc')
                ->limit(5)
                ->get();

            if ($listsWithPending->isNotEmpty()) {
                // Also recover any stuck 'validating' contacts (> 5 minutes old)
                Contact::where('validation_status', Contact::STATUS_VALIDATING)
                    ->where('updated_at', '<', now()->subMinutes(5))
                    ->update(['validation_status' => Contact::STATUS_PENDING]);

                foreach ($listsWithPending as $contactList) {
                    $remainingBudget = (int) floor($globalBudget - (microtime(true) - $validationStart));
                    if ($remainingBudget < 2) {
                        break;
                    }
                    ValidateContactsJob::dispatchSync($contactList, $remainingBudget);
                }
            }

            // If time remains in the validation budget, validate any direct campaign recipients
            $remainingBudget = (int) floor($globalBudget - (microtime(true) - $validationStart));
            if ($remainingBudget >= 3) {
                ValidateEmailsJob::dispatchSync($remainingBudget);
            }
        })
            ->everyMinute()
            ->name('validate-pending-contacts')
            ->withoutOverlapping(3);

        // 3. Process IMAP inbox (bounces and replies) every 5 minutes (up to 15s budget, round-robin)
        $schedule->call(function () use ($cronStart) {
            $elapsed = microtime(true) - $cronStart;
            $budget = (int) min(15, max(4, floor(52 - $elapsed)));
            ProcessImapInboxJob::dispatchSync($budget);
        })
            ->everyFiveMinutes()
            ->name('process-imap-inbox')
            ->withoutOverlapping(5);

        // 4. Daily reset of SMTP quotas and auto-resuming of paused campaigns
        $schedule->call(function (\App\Services\SmtpSelectorService $smtpSelector) {
            $smtpSelector->resetAllCounters();
        })->daily()->name('reset-smtp-quotas');

        // 5. Auto-resume SMTPs that were paused due to high bounce rate after 24 hours
        $schedule->call(function () {
            $pausedSmtps = SmtpConfig::where('auto_paused', true)
                ->whereNotNull('paused_at')
                ->where('paused_at', '<=', now()->subHours(24))
                ->get();

            foreach ($pausedSmtps as $smtp) {
                $smtp->autoResume();
                Log::info("Scheduler: Auto-resumed SMTP [{$smtp->name}] (ID: {$smtp->id}) after 24-hour pause period.");
            }
        })->hourly()->name('auto-resume-paused-smtps');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
