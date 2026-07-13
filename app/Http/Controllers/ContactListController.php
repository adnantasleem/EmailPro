<?php

namespace App\Http\Controllers;

use App\Models\ContactList;
use App\Models\Contact;
use App\Jobs\ValidateContactsJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContactListController extends Controller
{
    /**
     * Display a listing of contact lists.
     */
    public function index()
    {
        $contactLists = ContactList::where('user_id', auth()->id())
            ->withCount('contacts')
            ->latest()
            ->get();

        return view('contact-lists.index', compact('contactLists'));
    }

    /**
     * Show the form for creating a new contact list.
     */
    public function create()
    {
        return view('contact-lists.create');
    }

    /**
     * Store a newly created contact list.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'emails' => 'nullable|string',
            'file' => 'nullable|file|mimes:csv,txt|max:10240',
        ]);

        $contactList = ContactList::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        $result = ['imported' => 0, 'duplicates' => 0, 'global_duplicates' => 0, 'unsubscribed' => 0, 'blocklisted' => 0, 'disposable' => 0, 'invalid_syntax' => 0, 'skipped_emails' => [], 'valid_emails' => []];

        // Stage from text
        if (!empty($validated['emails'])) {
            $result = $contactList->stageImports($validated['emails'], auth()->id());
        }

        // Stage from CSV
        if ($request->hasFile('file')) {
            $content = file_get_contents($request->file('file')->path());
            $csvResult = $contactList->stageImports($content, auth()->id());
            $result['imported'] += $csvResult['imported'];
            $result['duplicates'] += $csvResult['duplicates'];
            $result['global_duplicates'] += $csvResult['global_duplicates'] ?? 0;
            $result['unsubscribed'] += $csvResult['unsubscribed'];
            $result['blocklisted'] += $csvResult['blocklisted'] ?? 0;
            $result['disposable'] += $csvResult['disposable'] ?? 0;
            $result['invalid_syntax'] += $csvResult['invalid_syntax'] ?? 0;
            $result['skipped_emails'] = array_merge($result['skipped_emails'], $csvResult['skipped_emails'] ?? []);
            $result['valid_emails'] = array_merge($result['valid_emails'], $csvResult['valid_emails'] ?? []);
        }

        // Save parsing result to temp file
        $importId = \Illuminate\Support\Str::uuid()->toString();
        Storage::put("temp_imports/{$importId}.json", json_encode($result));

        return redirect()->route('contact-lists.review-import', [
            'contact_list' => $contactList->id,
            'import_id' => $importId
        ]);
    }

    /**
     * Display the specified contact list.
     */
    public function show(ContactList $contactList, Request $request)
    {
        if ((int) $contactList->user_id !== auth()->id()) {
            abort(403);
        }

        $query = $contactList->contacts();

        // Search filter
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status = $request->get('status')) {
            if ($status !== 'all') {
                $query->where('validation_status', $status);
            }
        }

        $contacts = $query->orderBy('created_at', 'desc')
            ->paginate(50)
            ->appends($request->query()); // Preserve query params in pagination links

        return view('contact-lists.show', compact('contactList', 'contacts'));
    }

    /**
     * Show the form for editing the specified contact list.
     */
    public function edit(ContactList $contactList)
    {
        if ((int) $contactList->user_id !== auth()->id()) {
            abort(403);
        }

        return view('contact-lists.edit', compact('contactList'));
    }

    /**
     * Update the specified contact list.
     */
    public function update(Request $request, ContactList $contactList)
    {
        if ((int) $contactList->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $contactList->update($validated);

        return redirect()->route('contact-lists.show', $contactList)
            ->with('success', 'Contact list updated.');
    }

    /**
     * Remove the specified contact list.
     */
    public function destroy(ContactList $contactList)
    {
        if ((int) $contactList->user_id !== auth()->id()) {
            abort(403);
        }

        $contactList->delete();

        return redirect()->route('contact-lists.index')
            ->with('success', 'Contact list deleted.');
    }

    /**
     * Add more contacts to an existing list.
     */
    public function addContacts(Request $request, ContactList $contactList)
    {
        if ((int) $contactList->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'emails' => 'nullable|string',
            'file' => 'nullable|file|mimes:csv,txt|max:10240',
        ]);

        $result = ['imported' => 0, 'duplicates' => 0, 'global_duplicates' => 0, 'unsubscribed' => 0, 'blocklisted' => 0, 'disposable' => 0, 'invalid_syntax' => 0, 'skipped_emails' => [], 'valid_emails' => []];

        if (!empty($validated['emails'])) {
            $result = $contactList->stageImports($validated['emails'], auth()->id());
        }

        if ($request->hasFile('file')) {
            $content = file_get_contents($request->file('file')->path());
            $csvResult = $contactList->stageImports($content, auth()->id());
            $result['imported'] += $csvResult['imported'];
            $result['duplicates'] += $csvResult['duplicates'];
            $result['global_duplicates'] += $csvResult['global_duplicates'] ?? 0;
            $result['unsubscribed'] += $csvResult['unsubscribed'];
            $result['blocklisted'] += $csvResult['blocklisted'] ?? 0;
            $result['disposable'] += $csvResult['disposable'] ?? 0;
            $result['invalid_syntax'] += $csvResult['invalid_syntax'] ?? 0;
            $result['skipped_emails'] = array_merge($result['skipped_emails'], $csvResult['skipped_emails'] ?? []);
            $result['valid_emails'] = array_merge($result['valid_emails'], $csvResult['valid_emails'] ?? []);
        }

        $message = "{$result['imported']} contacts added.";
        if ($result['duplicates'] > 0) {
            $message .= " {$result['duplicates']} duplicates in this list.";
        }
        if (($result['global_duplicates'] ?? 0) > 0) {
            $message .= " {$result['global_duplicates']} already in other lists.";
        }
        if ($result['unsubscribed'] > 0) {
            $message .= " {$result['unsubscribed']} unsubscribed.";
        }
        if (($result['blocklisted'] ?? 0) > 0) {
            $message .= " {$result['blocklisted']} blocklisted.";
        }
        if (($result['disposable'] ?? 0) > 0) {
            $message .= " {$result['disposable']} disposable emails flagged.";
        }
        if (($result['invalid_syntax'] ?? 0) > 0) {
            $message .= " {$result['invalid_syntax']} invalid emails skipped.";
        }

        // Save parsing result to temp file
        $importId = \Illuminate\Support\Str::uuid()->toString();
        Storage::put("temp_imports/{$importId}.json", json_encode($result));

        return redirect()->route('contact-lists.review-import', [
            'contact_list' => $contactList->id, 
            'import_id' => $importId
        ]);
    }

    /**
     * Show the import review screen.
     */
    public function reviewImport(ContactList $contactList, string $importId)
    {
        if ((int) $contactList->user_id !== auth()->id()) {
            abort(403);
        }

        if (!Storage::exists("temp_imports/{$importId}.json")) {
            return redirect()->route('contact-lists.show', $contactList)
                ->with('error', 'Import session expired or not found.');
        }

        $importData = json_decode(Storage::get("temp_imports/{$importId}.json"), true);

        return view('contact-lists.review-import', compact('contactList', 'importId', 'importData'));
    }

    /**
     * Commit the staged import into the database.
     */
    public function commitImport(Request $request, ContactList $contactList, string $importId)
    {
        if ((int) $contactList->user_id !== auth()->id()) {
            abort(403);
        }

        if (!Storage::exists("temp_imports/{$importId}.json")) {
            return redirect()->route('contact-lists.show', $contactList)
                ->with('error', 'Import session expired or not found.');
        }

        $importData = json_decode(Storage::get("temp_imports/{$importId}.json"), true);
        
        // Save the staged VALID contacts
        $validEmails = $importData['valid_emails'] ?? [];
        $imported = 0;
        
        if (!empty($validEmails)) {
            $imported = $contactList->saveStagedContacts($validEmails);
            // Dispatch background job to do final validation
            ValidateContactsJob::dispatch($contactList);
        }
        
        // Cleanup temp file
        Storage::delete("temp_imports/{$importId}.json");

        return redirect()->route('contact-lists.show', $contactList)
            ->with('success', "{$imported} valid contacts have been added. Final validation is in progress.");
    }

    /**
     * Export all parsed contacts (Valid and Invalid) as CSV.
     */
    public function downloadImportReport(ContactList $contactList, string $importId)
    {
        if ((int) $contactList->user_id !== auth()->id()) {
            abort(403);
        }

        if (!Storage::exists("temp_imports/{$importId}.json")) {
            return redirect()->route('contact-lists.show', $contactList)
                ->with('error', 'Import report expired or not found.');
        }

        $importData = json_decode(Storage::get("temp_imports/{$importId}.json"), true);
        
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $contactList->name) . '_import_report_' . date('Y-m-d_His') . '.csv';

        // Create temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_');
        $handle = fopen($tempFile, 'w');
        
        // Write header
        fputcsv($handle, ['email', 'name', 'status']);
        
        // Write valid data rows
        $validEmails = $importData['valid_emails'] ?? [];
        foreach ($validEmails as $item) {
            fputcsv($handle, [
                $item['email'],
                $item['name'] ?? '',
                $item['status'],
            ]);
        }

        // Write skipped/invalid data rows
        $skippedEmails = $importData['skipped_emails'] ?? [];
        foreach ($skippedEmails as $item) {
            fputcsv($handle, [
                $item['email'],
                $item['name'] ?? '',
                $item['status'],
            ]);
        }
        fclose($handle);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'text/csv',
        ])->deleteFileAfterSend(true);
    }


    /**
     * Delete a contact from a list.
     */
    public function deleteContact(ContactList $contactList, Contact $contact)
    {
        if ((int) $contactList->user_id !== auth()->id()) {
            abort(403);
        }

        $contact->delete();
        $contactList->updateContactsCount();

        return redirect()->route('contact-lists.show', $contactList)
            ->with('success', 'Contact deleted.');
    }

    /**
     * Export contacts from a list as CSV with full details.
     */
    public function export(ContactList $contactList, Request $request)
    {
        if ((int) $contactList->user_id !== auth()->id()) {
            abort(403);
        }

        $status = $request->query('status', 'all');
        
        $query = $contactList->contacts();
        
        // Filter by status if specified
        switch ($status) {
            case 'valid':
                $query->where('validation_status', Contact::STATUS_VALID);
                $suffix = '_valid';
                break;
            case 'invalid':
                $query->where('validation_status', Contact::STATUS_INVALID);
                $suffix = '_invalid';
                break;
            case 'pending':
                $query->where('validation_status', Contact::STATUS_PENDING);
                $suffix = '_pending';
                break;
            case 'validating':
                $query->where('validation_status', Contact::STATUS_VALIDATING);
                $suffix = '_validating';
                break;
            default:
                $suffix = '_all';
        }
        
        $query->orderBy('id');
        
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $contactList->name) . $suffix . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // Collect unique custom field keys in a memory-efficient way
            $customFieldKeys = [];
            $queryClone = clone $query;
            $queryClone->chunk(1000, function ($contacts) use (&$customFieldKeys) {
                foreach ($contacts as $contact) {
                    if ($contact->custom_fields && is_array($contact->custom_fields)) {
                        foreach (array_keys($contact->custom_fields) as $key) {
                            if (!in_array($key, $customFieldKeys)) {
                                $customFieldKeys[] = $key;
                            }
                        }
                    }
                }
            });

            // Write CSV header
            $header = [
                'email',
                'name',
                'validation_status',
                'validation_error',
                'created_at',
                'validated_at'
            ];
            foreach ($customFieldKeys as $key) {
                $header[] = $key;
            }
            fputcsv($handle, $header);

            // Write data rows in chunks
            $query->chunk(1000, function ($contacts) use ($handle, $customFieldKeys) {
                foreach ($contacts as $contact) {
                    $row = [
                        $contact->email,
                        $contact->name ?? '',
                        $contact->validation_status,
                        $contact->validation_error ?? '',
                        $contact->created_at?->format('Y-m-d H:i:s') ?? '',
                        $contact->validated_at?->format('Y-m-d H:i:s') ?? ''
                    ];

                    foreach ($customFieldKeys as $key) {
                        $row[] = $contact->custom_fields[$key] ?? '';
                    }

                    fputcsv($handle, $row);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Expires' => '0',
        ]);
    }

    /**
     * Delete all invalid contacts from a list.
     */
    public function deleteInvalidContacts(ContactList $contactList)
    {
        if ((int) $contactList->user_id !== auth()->id()) {
            abort(403);
        }

        $count = $contactList->contacts()
            ->where('validation_status', Contact::STATUS_INVALID)
            ->count();

        $contactList->contacts()
            ->where('validation_status', Contact::STATUS_INVALID)
            ->delete();

        $contactList->updateContactsCount();

        return redirect()->route('contact-lists.show', $contactList)
            ->with('success', "{$count} invalid contacts deleted.");
    }

    /**
     * Download skipped emails from session as CSV.
     */
    public function downloadSkippedEmails(Request $request)
    {
        $skippedEmails = session('import_skipped_emails', []);
        $listName = session('import_list_name', 'contacts');

        if (empty($skippedEmails)) {
            return redirect()->back()->with('error', 'No skipped emails to download. The download is only available immediately after import.');
        }

        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $listName) . '_skipped_' . date('Y-m-d_His') . '.csv';

        // Create temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_');
        $handle = fopen($tempFile, 'w');
        
        // Write header
        fputcsv($handle, ['email', 'name', 'status']);
        
        // Write data rows
        foreach ($skippedEmails as $item) {
            fputcsv($handle, [
                $item['email'],
                $item['name'] ?? '',
                $item['status'],
            ]);
        }
        fclose($handle);

        // Re-flash the data so it's available if user goes back
        session()->flash('import_skipped_emails', $skippedEmails);
        session()->flash('import_list_name', $listName);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'text/csv',
        ])->deleteFileAfterSend(true);
    }
}
