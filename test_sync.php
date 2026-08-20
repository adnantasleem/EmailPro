<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\ContactList;
use App\Models\Contact;
use App\Models\Campaign;
use App\Models\Recipient;
use App\Jobs\ValidateContactsJob;
use Illuminate\Support\Facades\DB;

$user = User::first();

// Create list
$list = ContactList::create(['user_id' => $user->id, 'name' => 'Test List ' . time()]);
$contact = Contact::create([
    'contact_list_id' => $list->id,
    'email' => 'test_validate_sync_' . time() . '@example.com',
    'validation_status' => 'pending'
]);

// Create campaign
$campaign = Campaign::create([
    'user_id' => $user->id,
    'name' => 'Test Sync Campaign ' . time(),
    'status' => 'draft',
    'type' => 'regular'
]);

// Sync to campaign (like CampaignController does)
$campaign->contactLists()->attach($list->id);
$now = now()->toDateTimeString();
DB::insert("
    INSERT IGNORE INTO recipients (campaign_id, email, name, status, created_at, updated_at)
    SELECT ?, LOWER(email), name, 'pending', ?, ? FROM contacts WHERE contact_list_id = ?
", [$campaign->id, $now, $now, $list->id]);

echo "Recipient status before validation: " . Recipient::where('campaign_id', $campaign->id)->first()->status . "\n";

// Validate
$job = new ValidateContactsJob($list);
$validator = app(App\Services\EmailValidatorService::class);
$job->handle($validator);

echo "Recipient status after validation: " . Recipient::where('campaign_id', $campaign->id)->first()->status . "\n";
