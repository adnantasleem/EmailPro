<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\InvalidEmail;
use App\Models\Recipient;
use App\Models\Unsubscribe;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportCampaignRecipientsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Allow up to 2 hours for very large imports (216k+ contacts).
     */
    public int $timeout = 7200;

    /**
     * Only try once – if it fails, the campaign stays in "importing" status
     * and an error is logged rather than corrupting data with partial retries.
     */
    public int $tries = 1;

    public function __construct(
        protected int $campaignId,
        protected array $contactListIds
    ) {}

    public function handle(): void
    {
        $campaign = Campaign::find($this->campaignId);

        if (!$campaign) {
            Log::error("ImportCampaignRecipientsJob: Campaign {$this->campaignId} not found.");
            return;
        }

        Log::info("ImportCampaignRecipientsJob: Starting import for campaign {$this->campaignId}");

        // ------------------------------------------------------------------
        // 1. Build exclusion sets (in-memory – single queries, not per-row)
        // ------------------------------------------------------------------
        $unsubscribedEmails = Unsubscribe::where('user_id', $campaign->user_id)
            ->pluck('email')
            ->map(fn($e) => strtolower($e))
            ->flip()   // use as hash-map for O(1) lookups
            ->toArray();

        $invalidEmails = InvalidEmail::where('user_id', $campaign->user_id)
            ->pluck('email')
            ->map(fn($e) => strtolower($e))
            ->flip()
            ->toArray();

        // Emails already in this campaign (handles partial imports after a timeout)
        $existingEmails = Recipient::where('campaign_id', $this->campaignId)
            ->pluck('email')
            ->flip()
            ->toArray();

        // ------------------------------------------------------------------
        // 2. Process each list in chunks to keep memory low
        // ------------------------------------------------------------------
        $totalImported = 0;
        $chunkSize     = 500;   // rows inserted per bulk INSERT
        $now           = now()->toDateTimeString();

        foreach ($this->contactListIds as $listId) {
            $list = ContactList::where('id', $listId)
                ->where('user_id', $campaign->user_id)
                ->first();

            if (!$list) {
                continue;
            }

            $list->contacts()
                ->active()
                ->where('validation_status', '!=', Contact::STATUS_INVALID)
                ->chunkById(2000, function ($contacts) use (
                    $campaign,
                    &$existingEmails,
                    $unsubscribedEmails,
                    $invalidEmails,
                    &$totalImported,
                    $chunkSize,
                    $now
                ) {
                    $rows = [];

                    foreach ($contacts as $contact) {
                        $emailLower = strtolower($contact->email);

                        // Skip unsubscribed / blocklisted / duplicates
                        if (
                            isset($unsubscribedEmails[$emailLower]) ||
                            isset($invalidEmails[$emailLower])       ||
                            isset($existingEmails[$emailLower])
                        ) {
                            continue;
                        }

                        // Mark as seen so we don't insert it again in this run
                        $existingEmails[$emailLower] = true;

                        $recipientStatus  = Recipient::STATUS_PENDING;
                        $validationResult = null;

                        if ($contact->validation_status === Contact::STATUS_VALID) {
                            $recipientStatus  = Recipient::STATUS_VALID;
                            $validationResult = $contact->validation_result
                                ? json_encode($contact->validation_result)
                                : null;
                        }

                        $rows[] = [
                            'campaign_id'      => $campaign->id,
                            'email'            => $emailLower,
                            'name'             => $contact->name,
                            'custom_fields'    => $contact->custom_fields
                                ? json_encode($contact->custom_fields)
                                : null,
                            'status'           => $recipientStatus,
                            'validation_result'=> $validationResult,
                            'validated_at'     => $contact->validated_at
                                ? $contact->validated_at->toDateTimeString()
                                : null,
                            'created_at'       => $now,
                            'updated_at'       => $now,
                        ];

                        // Flush in sub-chunks to stay within MySQL's max_allowed_packet
                        if (count($rows) >= $chunkSize) {
                            DB::table('recipients')->insert($rows);
                            $totalImported += count($rows);
                            $rows = [];
                        }
                    }

                    // Insert whatever is left in this chunk
                    if (!empty($rows)) {
                        DB::table('recipients')->insert($rows);
                        $totalImported += count($rows);
                    }
                });
        }

        // ------------------------------------------------------------------
        // 3. Stamp the campaign so the UI knows the import is done
        // ------------------------------------------------------------------
        $campaign->update([
            'import_status'  => 'done',
            'imported_count' => $totalImported,
        ]);

        Log::info("ImportCampaignRecipientsJob: Finished – {$totalImported} recipients imported into campaign {$this->campaignId}");
    }

    public function failed(\Throwable $e): void
    {
        Log::error("ImportCampaignRecipientsJob: FAILED for campaign {$this->campaignId}: " . $e->getMessage());

        $campaign = Campaign::find($this->campaignId);
        if ($campaign) {
            $campaign->update(['import_status' => 'failed']);
        }
    }
}
