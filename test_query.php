<?php
$campaign = \App\Models\Campaign::first();
if (!$campaign) {
    echo 'No campaign found';
} else {
    $listIds = $campaign->contactLists->pluck('id')->toArray();
    if (empty($listIds)) {
        echo 'No contact lists';
    } else {
        $now = now()->toDateTimeString();
        $placeholders = implode(',', array_fill(0, count($listIds), '?'));
        $bindings = array_merge(
            [$campaign->id, $now, $now],
            $listIds,
            [$campaign->user_id, $campaign->user_id]
        );
        $query = "
            INSERT IGNORE INTO recipients (campaign_id, email, name, custom_fields, status, validation_result, validated_at, created_at, updated_at)
            SELECT 
                ?, 
                LOWER(c.email), 
                MAX(c.name), 
                MAX(c.custom_fields), 
                IF(MAX(c.validation_status) = 'valid', 'valid', 'pending'), 
                MAX(c.validation_result), 
                MAX(c.validated_at), 
                ?, 
                ?
            FROM contacts c
            WHERE c.contact_list_id IN ($placeholders)
              AND c.is_active = 1
              AND c.validation_status != 'invalid'
              AND LOWER(c.email) NOT IN (SELECT LOWER(email) FROM unsubscribes WHERE user_id = ?)
              AND LOWER(c.email) NOT IN (SELECT LOWER(email) FROM invalid_emails WHERE user_id = ?)
            GROUP BY LOWER(c.email)
        ";
        try {
            $affected = \Illuminate\Support\Facades\DB::insert($query, $bindings);
            echo 'Inserted: ' . $affected;
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage();
        }
    }
}
