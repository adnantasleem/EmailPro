<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ContactList;
use App\Jobs\ValidateContactsJob;

class TestValidateCommand extends Command
{
    protected $signature = 'test:validate {id}';
    protected $description = 'Test ValidateContactsJob synchronously';

    public function handle()
    {
        $id = $this->argument('id');
        $this->info("Fetching ContactList $id...");
        $list = ContactList::findOrFail($id);
        
        $this->info("List found: " . $list->name);
        
        $this->info("Pending count: " . $list->contacts()->pendingValidation()->count());
        
        $this->info("Executing job synchronously...");
        $job = new ValidateContactsJob($list);
        
        try {
            // Provide a mock or the actual service
            $service = app(\App\Services\EmailValidatorService::class);
            $job->handle($service);
            $this->info("Job finished successfully!");
        } catch (\Throwable $e) {
            $this->error("Exception caught: " . $e->getMessage());
            $this->error($e->getTraceAsString());
        }
    }
}
