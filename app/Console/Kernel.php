<?php

namespace App\Console;

use App\Jobs\ValidateContactsJob;
use App\Models\ContactList;
use App\Models\Contact;
use App\Models\SmtpConfig;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Send emails every minute
        $schedule->job(new \App\Jobs\SendEmailsJob())
            ->everyMinute()
            ->name('send-emails')
            ->withoutOverlapping();

        // Daily reset of SMTP quotas and auto-resuming of paused campaigns
        $schedule->call(function (\App\Services\SmtpSelectorService $smtpSelector) {
            $smtpSelector->resetAllCounters();
        })->daily()->name('reset-smtp-quotas');

        // Auto-resume SMTPs that were paused due to high bounce rate after 24 hours
        $schedule->call(function () {
            $pausedSmtps = SmtpConfig::where('auto_paused', true)
                ->whereNotNull('paused_at')
                ->where('paused_at', '<=', now()->subHours(24))
                ->get();

            foreach ($pausedSmtps as $smtp) {
                $smtp->autoResume();
                Log::info("Scheduler: Auto-resumed SMTP [{$smtp->name}] (ID: {$smtp->id}) after 24-hour pause period.");
            }

            if ($pausedSmtps->isNotEmpty()) {
                Log::info("Scheduler: Auto-resumed {$pausedSmtps->count()} SMTP(s) after 24-hour bounce pause.");
            }
        })->hourly()->name('auto-resume-paused-smtps');

        // Validate pending contacts every minute
        // This ensures large contact lists continue to be validated
        $schedule->call(function () {
            // First, reset any contacts stuck in 'validating' status for more than 5 minutes
            // This handles cases where the job crashed/timed out mid-process
            $stuckCount = Contact::where('validation_status', Contact::STATUS_VALIDATING)
                ->where('updated_at', '<', now()->subMinutes(5))
                ->update(['validation_status' => Contact::STATUS_PENDING]);
            
            if ($stuckCount > 0) {
                Log::info("Scheduler: Reset {$stuckCount} stuck validating contacts back to pending");
            }

            // Find all contact lists that have pending contacts
            $listsWithPending = ContactList::whereHas('contacts', function ($query) {
                $query->where('validation_status', Contact::STATUS_PENDING);
            })->get();

            foreach ($listsWithPending as $contactList) {
                $pendingCount = $contactList->contacts()
                    ->where('validation_status', Contact::STATUS_PENDING)
                    ->count();

                if ($pendingCount > 0) {
                    Log::info("Scheduler: Dispatching ValidateContactsJob for list {$contactList->id} ({$contactList->name}) - {$pendingCount} pending contacts");
                    ValidateContactsJob::dispatch($contactList);
                }
            }
        })
            ->everyMinute()
            ->name('validate-pending-contacts')
            ->withoutOverlapping();
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
