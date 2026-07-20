<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Recipient;
use App\Models\SubjectLine;
use App\Models\BodyTemplate;
use App\Services\ContentRotatorService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    /**
     * Display a listing of campaigns.
     */
    public function index()
    {
        $campaigns = Campaign::where('user_id', auth()->id())
            ->latest()
            ->get()
            ->map(function ($campaign) {
            return [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'status' => $campaign->status,
                'stats' => $campaign->stats,
                'scheduled_at' => $campaign->scheduled_at?->format('M d, Y H:i'),
                'started_at' => $campaign->started_at?->format('M d, Y H:i'),
                'completed_at' => $campaign->completed_at?->format('M d, Y H:i'),
                'created_at' => $campaign->created_at->format('M d, Y'),
            ];
        });

        return view('campaigns.index', compact('campaigns'));
    }

    /**
     * Show the form for creating a new campaign.
     */
    public function create()
    {
        $contactLists = \App\Models\ContactList::where('user_id', auth()->id())
            ->withCount('contacts')
            ->orderBy('name')
            ->get();

        $subjectLines = \App\Models\SubjectLine::where('user_id', auth()->id())
            ->whereNull('campaign_id')
            ->orderBy('subject')
            ->get();

        $bodyTemplates = \App\Models\BodyTemplate::where('user_id', auth()->id())
            ->whereNull('campaign_id')
            ->orderBy('name')
            ->get();

        $subjectGroups = \App\Models\SubjectGroup::where('user_id', auth()->id())
            ->withCount('subjectLines')
            ->orderBy('name')
            ->get();

        $bodyGroups = \App\Models\BodyGroup::where('user_id', auth()->id())
            ->withCount('bodyTemplates')
            ->orderBy('name')
            ->get();

        $smtpConfigs = \App\Models\SmtpConfig::where('user_id', auth()->id())->active()->orderBy('name')->get();

        return view('campaigns.create', compact('contactLists', 'subjectLines', 'bodyTemplates', 'subjectGroups', 'bodyGroups', 'smtpConfigs'));
    }

    /**
     * Store a newly created campaign.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'from_name' => 'nullable|string|max:255',
            'reply_to' => 'nullable|email|max:255',
            'use_all_smtps' => 'boolean',
            'smtp_configs' => 'nullable|array',
            'smtp_configs.*' => 'exists:smtp_configs,id',
            'scheduled_at' => 'nullable|date|after:now',
            'saved_subject_groups' => 'nullable|array',
            'saved_subject_groups.*' => 'exists:subject_groups,id',
            'saved_subjects' => 'nullable|array',
            'saved_subjects.*' => 'exists:subject_lines,id',
            'subjects' => 'nullable|array',
            'subjects.*' => 'nullable|string|max:500',
            'saved_body_groups' => 'nullable|array',
            'saved_body_groups.*' => 'exists:body_groups,id',
            'saved_bodies' => 'nullable|array',
            'saved_bodies.*' => 'exists:body_templates,id',
            'bodies' => 'nullable|array',
            'bodies.*.html' => 'nullable|string',
            'bodies.*.plain' => 'nullable|string',
            'contact_lists' => 'required|array|min:1',
            'contact_lists.*' => 'exists:contact_lists,id',
        ]);

        // Resolve subject groups to individual subject IDs
        $allSavedSubjectIds = collect($validated['saved_subjects'] ?? []);
        if (!empty($validated['saved_subject_groups'])) {
            $groupSubjectIds = \App\Models\SubjectLine::where('user_id', auth()->id())
                ->whereIn('subject_group_id', $validated['saved_subject_groups'])
                ->pluck('id');
            $allSavedSubjectIds = $allSavedSubjectIds->merge($groupSubjectIds)->unique();
        }

        // Resolve body groups to individual body IDs
        $allSavedBodyIds = collect($validated['saved_bodies'] ?? []);
        if (!empty($validated['saved_body_groups'])) {
            $groupBodyIds = \App\Models\BodyTemplate::where('user_id', auth()->id())
                ->whereIn('body_group_id', $validated['saved_body_groups'])
                ->pluck('id');
            $allSavedBodyIds = $allSavedBodyIds->merge($groupBodyIds)->unique();
        }

        $hasSubject = $allSavedSubjectIds->isNotEmpty() || 
                      (isset($validated['subjects']) && collect($validated['subjects'])->filter()->isNotEmpty());
        $hasBody = $allSavedBodyIds->isNotEmpty() || 
                   (isset($validated['bodies']) && collect($validated['bodies'])->filter(fn($b) => !empty($b['html']))->isNotEmpty());

        $errors = [];
        if (!$hasSubject) {
            $errors['subjects'] = 'Please add or select at least one subject line.';
        }
        if (!$hasBody) {
            $errors['bodies'] = 'Please add or select at least one body template.';
        }

        if (!empty($errors)) {
            return redirect()->back()->withInput()->withErrors($errors);
        }

        $useAllSmtps = $request->has('use_all_smtps');

        // Create campaign
        $campaign = Campaign::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'from_name' => $validated['from_name'] ?? null,
            'reply_to' => $validated['reply_to'] ?? null,
            'status' => Campaign::STATUS_DRAFT,
            'use_all_smtps' => $useAllSmtps,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
        ]);

        if (!$useAllSmtps && !empty($validated['smtp_configs'])) {
            $campaign->smtpConfigs()->attach($validated['smtp_configs']);
        }

        // Add saved subject lines (from groups + individual, deduplicated)
        if ($allSavedSubjectIds->isNotEmpty()) {
            foreach ($allSavedSubjectIds as $subjectId) {
                $savedSubject = \App\Models\SubjectLine::where('id', $subjectId)
                    ->where('user_id', auth()->id())
                    ->first();
                if ($savedSubject) {
                    $campaign->subjectLines()->create(['subject' => $savedSubject->subject]);
                }
            }
        }

        // Add new subject lines
        if (!empty($validated['subjects'])) {
            foreach ($validated['subjects'] as $subject) {
                if (!empty(trim($subject))) {
                    $campaign->subjectLines()->create(['subject' => $subject]);
                }
            }
        }

        // Add saved body templates (from groups + individual, deduplicated)
        if ($allSavedBodyIds->isNotEmpty()) {
            foreach ($allSavedBodyIds as $bodyId) {
                $savedBody = \App\Models\BodyTemplate::where('id', $bodyId)
                    ->where('user_id', auth()->id())
                    ->first();
                if ($savedBody) {
                    $campaign->bodyTemplates()->create([
                        'name' => $savedBody->name,
                        'html_content' => $savedBody->html_content,
                        'plain_content' => $savedBody->plain_content,
                    ]);
                }
            }
        }

        // Add new body templates
        if (!empty($validated['bodies'])) {
            foreach ($validated['bodies'] as $body) {
                if (!empty(trim($body['html'] ?? ''))) {
                    $campaign->bodyTemplates()->create([
                        'name' => 'Custom Template',
                        'html_content' => $body['html'],
                        'plain_content' => $body['plain'] ?? null,
                    ]);
                }
            }
        }

        // Import from selected contact lists
        if (!empty($validated['contact_lists'])) {
            $campaign->contactLists()->attach($validated['contact_lists']);
            
            $campaign->update(['import_status' => 'importing']);
            \App\Jobs\ImportCampaignRecipientsJob::dispatch($campaign->id, $validated['contact_lists']);
        }

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', "Campaign created. Contacts are being imported in the background.");
    }

    /**
     * Display the specified campaign.
     */
    public function show(Campaign $campaign, ContentRotatorService $rotator)
    {
        // Ensure user owns this campaign
        if ((int) $campaign->user_id !== auth()->id()) {
            abort(403);
        }

        $stats = $campaign->stats;

        // Add opened count to stats
        $stats['opened'] = $campaign->recipients()->whereNotNull('opened_at')->count();

        $subjectStats = $rotator->getSubjectStats($campaign);
        $bodyStats = $rotator->getBodyStats($campaign);

        $recentLogs = $campaign->emailLogs()
            ->with(['recipient', 'smtpConfig', 'subjectLine'])
            ->latest()
            ->limit(20)
            ->get();

        $failedRecipients = $campaign->recipients()
            ->where('status', Recipient::STATUS_FAILED)
            ->limit(50)
            ->get();

        $openedRecipients = $campaign->recipients()
            ->whereNotNull('opened_at')
            ->orderBy('opened_at', 'desc')
            ->limit(50)
            ->get();

        $smtpConfigs = \App\Models\SmtpConfig::where('user_id', auth()->id())->active()->orderBy('name')->get();

        return view('campaigns.show', compact(
            'campaign',
            'stats',
            'subjectStats',
            'bodyStats',
            'recentLogs',
            'failedRecipients',
            'openedRecipients',
            'smtpConfigs'
        ));
    }

    /**
     * Display a comprehensive, printable report for the specified campaign.
     */
    public function report(Campaign $campaign, ContentRotatorService $rotator)
    {
        // Ensure user owns this campaign
        if ((int) $campaign->user_id !== auth()->id()) {
            abort(403);
        }

        $stats = $campaign->stats;

        // Add opened count to stats
        $stats['opened'] = $campaign->recipients()->whereNotNull('opened_at')->count();

        $subjectStats = $rotator->getSubjectStats($campaign);
        $bodyStats = $rotator->getBodyStats($campaign);

        // Calculate duration if completed
        $duration = null;
        if ($campaign->started_at && $campaign->completed_at) {
            $duration = $campaign->started_at->diffForHumans($campaign->completed_at, true);
        }

        // Get all failed recipients to aggregate errors
        $failedRecipients = $campaign->recipients()
            ->where('status', \App\Models\Recipient::STATUS_FAILED)
            ->get();
            
        $errorCategories = [];
        foreach ($failedRecipients as $r) {
            $msg = $r->error_message ?? 'Unknown error';
            if (str_contains($msg, 'timed out') || str_contains($msg, 'Connection timed out')) {
                $cat = 'Connection Timeout';
            } elseif (str_contains($msg, 'closed unexpectedly')) {
                $cat = 'Connection Closed';
            } elseif (str_contains($msg, 'Name or service not known') || str_contains($msg, 'getaddrinfo')) {
                $cat = 'DNS Resolution Failed';
            } elseif (str_contains($msg, 'sending limit') || str_contains($msg, 'quota') || str_contains($msg, '550')) {
                $cat = 'Sending Limit / Quota';
            } elseif (str_contains($msg, 'No available SMTP')) {
                $cat = 'No SMTP Available';
            } elseif (str_contains($msg, 'refused') || str_contains($msg, 'Could not establish')) {
                $cat = 'Connection Refused';
            } else {
                $cat = 'Other Error';
            }
            $errorCategories[$cat] = ($errorCategories[$cat] ?? 0) + 1;
        }
        arsort($errorCategories);

        // Daily breakdown
        $dailyStats = $campaign->recipients()
            ->selectRaw('DATE(COALESCE(sent_at, updated_at)) as date')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as sent', [\App\Models\Recipient::STATUS_SENT])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed', [\App\Models\Recipient::STATUS_FAILED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as bounced', [\App\Models\Recipient::STATUS_BOUNCED])
            ->whereIn('status', [
                \App\Models\Recipient::STATUS_SENT, 
                \App\Models\Recipient::STATUS_FAILED, 
                \App\Models\Recipient::STATUS_BOUNCED
            ])
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        return view('campaigns.report', compact(
            'campaign',
            'stats',
            'subjectStats',
            'bodyStats',
            'duration',
            'errorCategories',
            'dailyStats'
        ));
    }

    /**
     * Show the form for editing the specified campaign.
     */
    public function edit(Campaign $campaign)
    {
        if (!in_array($campaign->status, [Campaign::STATUS_DRAFT, Campaign::STATUS_PAUSED, Campaign::STATUS_COMPLETED])) {
            return redirect()->route('campaigns.show', $campaign)
                ->with('error', 'Cannot edit a campaign that is in progress.');
        }

        $subjectLines = $campaign->subjectLines;
        $bodyTemplates = $campaign->bodyTemplates;
        
        // Get saved subject lines (not yet attached to this campaign)
        $savedSubjectLines = \App\Models\SubjectLine::where('user_id', auth()->id())
            ->whereNull('campaign_id')
            ->orderBy('subject')
            ->get();

        // Get saved body templates (not yet attached to this campaign)
        $savedBodyTemplates = \App\Models\BodyTemplate::where('user_id', auth()->id())
            ->whereNull('campaign_id')
            ->orderBy('name')
            ->get();
        
        // Get contact lists with contact count
        $contactLists = \App\Models\ContactList::where('user_id', auth()->id())
            ->withCount('contacts')
            ->get();
        
        // Get currently selected contact list IDs
        $selectedContactLists = $campaign->contactLists->pluck('id')->toArray();

        // Get subject groups
        $subjectGroups = \App\Models\SubjectGroup::where('user_id', auth()->id())
            ->withCount('subjectLines')
            ->orderBy('name')
            ->get();

        // Get body groups
        $bodyGroups = \App\Models\BodyGroup::where('user_id', auth()->id())
            ->withCount('bodyTemplates')
            ->orderBy('name')
            ->get();

        $smtpConfigs = \App\Models\SmtpConfig::where('user_id', auth()->id())->active()->orderBy('name')->get();
        $selectedSmtps = $campaign->smtpConfigs->pluck('id')->toArray();

        return view('campaigns.edit', compact(
            'campaign', 
            'subjectLines', 
            'bodyTemplates', 
            'savedSubjectLines',
            'savedBodyTemplates',
            'subjectGroups',
            'bodyGroups',
            'contactLists', 
            'selectedContactLists',
            'smtpConfigs',
            'selectedSmtps'
        ));
    }

    /**
     * Update the specified campaign.
     */
    public function update(Request $request, Campaign $campaign)
    {
        if (!in_array($campaign->status, [Campaign::STATUS_DRAFT, Campaign::STATUS_PAUSED])) {
            return redirect()->route('campaigns.show', $campaign)
                ->with('error', 'Cannot update a campaign that is in progress.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'from_name' => 'nullable|string|max:255',
            'reply_to' => 'nullable|email|max:255',
            'use_all_smtps' => 'boolean',
            'smtp_configs' => 'nullable|array',
            'smtp_configs.*' => 'exists:smtp_configs,id',
            'scheduled_at' => 'nullable|date',
            'saved_subject_groups' => 'nullable|array',
            'saved_subject_groups.*' => 'exists:subject_groups,id',
            'saved_subjects' => 'nullable|array',
            'saved_subjects.*' => 'exists:subject_lines,id',
            'subjects' => 'nullable|array',
            'subjects.*' => 'nullable|string|max:500',
            'subject_ids' => 'nullable|array',
            'saved_body_groups' => 'nullable|array',
            'saved_body_groups.*' => 'exists:body_groups,id',
            'saved_bodies' => 'nullable|array',
            'saved_bodies.*' => 'exists:body_templates,id',
            'bodies' => 'nullable|array',
            'bodies.*.html' => 'nullable|string',
            'bodies.*.plain' => 'nullable|string',
            'body_ids' => 'nullable|array',
            'contact_lists' => 'nullable|array',
            'contact_lists.*' => 'exists:contact_lists,id',
        ]);

        $useAllSmtps = $request->has('use_all_smtps');

        // Update campaign basic info
        $campaign->update([
            'name' => $validated['name'],
            'from_name' => $validated['from_name'] ?? null,
            'reply_to' => $validated['reply_to'] ?? null,
            'use_all_smtps' => $useAllSmtps,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
        ]);
        
        if (!$useAllSmtps && !empty($validated['smtp_configs'])) {
            $campaign->smtpConfigs()->sync($validated['smtp_configs']);
        } else {
            $campaign->smtpConfigs()->detach();
        }

        // Resolve subject groups + individual subjects (deduplicated)
        $allSavedSubjectIds = collect($validated['saved_subjects'] ?? []);
        if (!empty($validated['saved_subject_groups'])) {
            $groupSubjectIds = \App\Models\SubjectLine::where('user_id', auth()->id())
                ->whereIn('subject_group_id', $validated['saved_subject_groups'])
                ->pluck('id');
            $allSavedSubjectIds = $allSavedSubjectIds->merge($groupSubjectIds)->unique();
        }

        if ($allSavedSubjectIds->isNotEmpty()) {
            $existingSubjects = \App\Models\SubjectLine::whereIn('id', $allSavedSubjectIds->toArray())
                ->where('user_id', auth()->id())
                ->get();
            
            foreach ($existingSubjects as $savedSubject) {
                $campaign->subjectLines()->create([
                    'user_id' => auth()->id(),
                    'subject' => $savedSubject->subject,
                ]);
            }
        }

        // Resolve body groups + individual bodies (deduplicated)
        $allSavedBodyIds = collect($validated['saved_bodies'] ?? []);
        if (!empty($validated['saved_body_groups'])) {
            $groupBodyIds = \App\Models\BodyTemplate::where('user_id', auth()->id())
                ->whereIn('body_group_id', $validated['saved_body_groups'])
                ->pluck('id');
            $allSavedBodyIds = $allSavedBodyIds->merge($groupBodyIds)->unique();
        }

        if ($allSavedBodyIds->isNotEmpty()) {
            $existingBodies = \App\Models\BodyTemplate::whereIn('id', $allSavedBodyIds->toArray())
                ->where('user_id', auth()->id())
                ->get();
            
            foreach ($existingBodies as $savedBody) {
                $campaign->bodyTemplates()->create([
                    'user_id' => auth()->id(),
                    'name' => $savedBody->name,
                    'html_content' => $savedBody->html_content,
                    'plain_content' => $savedBody->plain_content,
                ]);
            }
        }


        // Update subject lines
        if (!empty($request->subjects)) {
            $subjectIds = $request->subject_ids ?? [];
            $submittedSubjectIds = [];
            
            foreach ($request->subjects as $index => $subject) {
                if (empty(trim($subject))) continue;
                
                $subjectId = $subjectIds[$index] ?? null;
                if ($subjectId) {
                    // Update existing subject line
                    \App\Models\SubjectLine::where('id', $subjectId)
                        ->where('campaign_id', $campaign->id)
                        ->update(['subject' => trim($subject)]);
                    $submittedSubjectIds[] = $subjectId;
                } else {
                    // Create new subject line
                    $newSubject = \App\Models\SubjectLine::create([
                        'campaign_id' => $campaign->id,
                        'subject' => trim($subject),
                    ]);
                    $submittedSubjectIds[] = $newSubject->id;
                }
            }
            
            // Delete removed subject lines (only those originally from this campaign)
            \App\Models\SubjectLine::where('campaign_id', $campaign->id)
                ->whereNotIn('id', $submittedSubjectIds)
                ->delete();
        }

        // Update body templates
        if (!empty($request->bodies)) {
            $bodyIds = $request->body_ids ?? [];
            $submittedBodyIds = [];
            
            foreach ($request->bodies as $index => $body) {
                if (empty(trim($body['html'] ?? ''))) continue;
                
                $bodyId = $bodyIds[$index] ?? null;
                $plainContent = !empty($body['plain']) ? $body['plain'] : strip_tags($body['html']);
                
                if ($bodyId) {
                    // Update existing body template
                    \App\Models\BodyTemplate::where('id', $bodyId)
                        ->where('campaign_id', $campaign->id)
                        ->update([
                            'html_content' => $body['html'],
                            'plain_content' => $plainContent,
                        ]);
                    $submittedBodyIds[] = $bodyId;
                } else {
                    // Create new body template
                    $newBody = \App\Models\BodyTemplate::create([
                        'campaign_id' => $campaign->id,
                        'html_content' => $body['html'],
                        'plain_content' => $plainContent,
                    ]);
                    $submittedBodyIds[] = $newBody->id;
                }
            }
            
            // Delete removed body templates (only those originally from this campaign)
            \App\Models\BodyTemplate::where('campaign_id', $campaign->id)
                ->whereNotIn('id', $submittedBodyIds)
                ->delete();
        } elseif (empty($request->bodies) && empty($validated['saved_bodies'])) {
            // If no bodies submitted at all and no saved bodies added, keep existing
            // But if user intentionally removed all, we might need different logic
        }

        // Update contact lists and import new contacts
        if ($request->has('contact_lists')) {
            $newListIds = $request->contact_lists ?? [];
            $currentListIds = $campaign->contactLists->pluck('id')->toArray();
            
            // Find newly added lists (lists that weren't previously attached)
            $addedListIds = array_diff($newListIds, $currentListIds);
            
            // Sync the relationship
            $campaign->contactLists()->sync($newListIds);
            
            // Import contacts from newly added lists
            if (!empty($addedListIds)) {
                $campaign->update(['import_status' => 'importing']);
                \App\Jobs\ImportCampaignRecipientsJob::dispatch($campaign->id, array_values($addedListIds));
                
                return redirect()->route('campaigns.show', $campaign)
                    ->with('success', "Campaign updated. New contacts are being imported in the background.");
            }
        }

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', 'Campaign updated successfully.');
    }

    /**
     * Remove the specified campaign.
     */
    public function destroy(Campaign $campaign)
    {
        if (in_array($campaign->status, [Campaign::STATUS_VALIDATING, Campaign::STATUS_SENDING])) {
            return redirect()->route('campaigns.index')
                ->with('error', 'Cannot delete a campaign that is in progress.');
        }

        $campaign->delete();

        return redirect()->route('campaigns.index')->with('success', 'Campaign deleted successfully.');
    }

    /**
     * Send a test email for the campaign.
     */
    public function sendTestEmail(Request $request, Campaign $campaign, \App\Services\EmailSenderService $emailSender)
    {
        if ((int) $campaign->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'test_email' => 'required|email',
            'smtp_id' => 'required|exists:smtp_configs,id',
            'subject_id' => 'required|exists:subject_lines,id',
            'body_id' => 'required|exists:body_templates,id',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
        ]);

        $variableData = [
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'name' => trim(($validated['first_name'] ?? '') . ' ' . ($validated['last_name'] ?? '')),
        ];

        $result = $emailSender->sendTestEmail(
            $campaign,
            $validated['test_email'],
            $validated['smtp_id'],
            $validated['subject_id'],
            $validated['body_id'],
            $variableData
        );

        if ($result['success']) {
            return back()->with('success', 'Test email sent successfully!');
        } else {
            return back()->with('error', 'Failed to send test email: ' . $result['error']);
        }
    }

    /**
     * Start the campaign (go directly to SENDING - contacts are pre-validated).
     */
    public function start(Campaign $campaign)
    {
        if ($campaign->status !== Campaign::STATUS_DRAFT) {
            return redirect()->route('campaigns.show', $campaign)
                ->with('error', 'Campaign can only be started from draft status.');
        }

        if ($campaign->subjectLines()->count() === 0) {
            return redirect()->route('campaigns.show', $campaign)
                ->with('error', 'Campaign must have at least one subject line.');
        }

        if ($campaign->bodyTemplates()->count() === 0) {
            return redirect()->route('campaigns.show', $campaign)
                ->with('error', 'Campaign must have at least one body template.');
        }

        if ($campaign->recipients()->count() === 0) {
            return redirect()->route('campaigns.show', $campaign)
                ->with('error', 'Campaign must have at least one recipient.');
        }

        // Skip VALIDATING phase - contacts are pre-validated at upload time
        // Go directly to SENDING
        $campaign->update([
            'status' => Campaign::STATUS_SENDING,
            'started_at' => now(),
        ]);

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', 'Campaign started! Sending emails...');
    }

    /**
     * Pause the campaign.
     */
    public function pause(Campaign $campaign)
    {
        if ($campaign->status !== Campaign::STATUS_SENDING) {
            return redirect()->route('campaigns.show', $campaign)
                ->with('error', 'Can only pause campaigns that are sending.');
        }

        $campaign->update([
            'status' => Campaign::STATUS_PAUSED,
            'pause_reason' => Campaign::PAUSE_REASON_MANUAL,
        ]);

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', 'Campaign paused.');
    }

    /**
     * Resume the campaign.
     */
    public function resume(Campaign $campaign)
    {
        if ($campaign->status !== Campaign::STATUS_PAUSED) {
            return redirect()->route('campaigns.show', $campaign)
                ->with('error', 'Can only resume paused campaigns.');
        }

        $campaign->update([
            'status' => Campaign::STATUS_SENDING,
            'pause_reason' => null,
        ]);

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', 'Campaign resumed.');
    }

    /**
     * Stop the campaign and reset to draft.
     */
    public function stop(Campaign $campaign)
    {
        if (!in_array($campaign->status, [Campaign::STATUS_VALIDATING, Campaign::STATUS_SENDING, Campaign::STATUS_PAUSED])) {
            return redirect()->route('campaigns.show', $campaign)
                ->with('error', 'Campaign is not active.');
        }

        $campaign->update([
            'status' => Campaign::STATUS_DRAFT,
            'started_at' => null,
            'completed_at' => null,
        ]);

        // 1. Reset sent/failed recipients back to valid (these were already validated)
        $campaign->recipients()
            ->whereIn('status', [Recipient::STATUS_SENT, Recipient::STATUS_FAILED])
            ->update([
                'status' => Recipient::STATUS_VALID,
                'sent_at' => null,
                'error_message' => null,
            ]);

        // 2. For pending recipients, check if their contact is already validated in the contact list
        //    Only mark as valid if the contact list contact is valid (pre-validated)
        $pendingRecipients = $campaign->recipients()
            ->whereIn('status', [Recipient::STATUS_PENDING, Recipient::STATUS_VALIDATING])
            ->get();

        foreach ($pendingRecipients as $recipient) {
            $contact = \App\Models\Contact::whereHas('contactList', function ($q) use ($campaign) {
                    $q->where('user_id', $campaign->user_id);
                })
                ->where('email', $recipient->email)
                ->where('validation_status', \App\Models\Contact::STATUS_VALID)
                ->first();

            if ($contact) {
                $recipient->update(['status' => Recipient::STATUS_VALID]);
            }
        }

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', 'Campaign stopped and reset.');
    }

    /**
     * Restart a completed campaign (allows re-sending).
     */
    public function restart(Campaign $campaign)
    {
        if ($campaign->status !== Campaign::STATUS_COMPLETED) {
            return redirect()->route('campaigns.show', $campaign)
                ->with('error', 'Only completed campaigns can be restarted.');
        }

        // Reset campaign status to draft
        $campaign->update([
            'status' => Campaign::STATUS_DRAFT,
            'started_at' => null,
            'completed_at' => null,
        ]);

        // 1. Reset sent/failed recipients back to valid (these were already validated)
        $campaign->recipients()
            ->whereIn('status', [Recipient::STATUS_SENT, Recipient::STATUS_FAILED])
            ->update([
                'status' => Recipient::STATUS_VALID,
                'sent_at' => null,
                'error_message' => null,
            ]);

        // 2. For pending recipients, check if their contact is validated in the contact list
        $pendingRecipients = $campaign->recipients()
            ->whereIn('status', [Recipient::STATUS_PENDING, Recipient::STATUS_VALIDATING])
            ->get();

        foreach ($pendingRecipients as $recipient) {
            $contact = \App\Models\Contact::whereHas('contactList', function ($q) use ($campaign) {
                    $q->where('user_id', $campaign->user_id);
                })
                ->where('email', $recipient->email)
                ->where('validation_status', \App\Models\Contact::STATUS_VALID)
                ->first();

            if ($contact) {
                $recipient->update(['status' => Recipient::STATUS_VALID]);
            }
        }

        // Reset subject line usage counts
        $campaign->subjectLines()->update(['usage_count' => 0]);
        
        // Reset body template usage counts
        $campaign->bodyTemplates()->update(['usage_count' => 0]);

        $validCount = $campaign->recipients()->where('status', Recipient::STATUS_VALID)->count();

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', "Campaign restarted! {$validCount} recipients ready to receive emails again.");
    }

    /**
     * Import recipients from text (email per line or comma-separated).
     */
    protected function importRecipients(Campaign $campaign, string $emailsText, array $unsubscribedEmails = []): int
    {
        // Split by newlines or commas
        $lines = preg_split('/[\r\n,]+/', $emailsText);
        $imported = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Check if line has name and email (format: "Name <email>" or "email")
            if (preg_match('/^(.+?)\s*<(.+?)>$/', $line, $matches)) {
                $name = trim($matches[1]);
                $email = strtolower(trim($matches[2]));
            } else {
                $name = null;
                $email = strtolower($line);
            }

            // Basic email format check
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            // Check if unsubscribed
            if (in_array($email, $unsubscribedEmails)) {
                continue;
            }

            // Check if in invalid emails blocklist
            $invalidEmails = \App\Models\InvalidEmail::getEmailsArray($campaign->user_id);
            if (in_array($email, $invalidEmails)) {
                continue;
            }

            // Check for duplicates in this campaign
            if ($campaign->recipients()->where('email', $email)->exists()) {
                continue;
            }

            $campaign->recipients()->create([
                'email' => $email,
                'name' => $name,
                'status' => Recipient::STATUS_PENDING,
            ]);

            $imported++;
        }

        return $imported;
    }

    /**
     * Export campaign recipients to CSV.
     */
    public function export(Campaign $campaign)
    {
        if ((int) $campaign->user_id !== auth()->id()) {
            abort(403);
        }

        $filename = "campaign_{$campaign->id}_export.csv";

        return response()->streamDownload(function () use ($campaign) {
            // Prevent PHP script timeout
            set_time_limit(0);

            $handle = fopen('php://output', 'w');

            // Write CSV header
            fputcsv($handle, [
                'email',
                'name',
                'status',
                'sent_at',
                'opened_at',
                'open_count',
                'error_message'
            ]);

            // Flush immediately so Nginx/browser receives headers and prevents timeout
            if (ob_get_level() > 0) ob_flush();
            flush();

            // Write data rows in chunks using DB for faster performance
            \Illuminate\Support\Facades\DB::table('recipients')
                ->where('campaign_id', $campaign->id)
                ->orderBy('id')
                ->chunk(5000, function ($recipients) use ($handle) {
                    foreach ($recipients as $recipient) {
                        $status = $recipient->status;
                        if ($status === \App\Models\Recipient::STATUS_SENT) {
                            $status = 'delivered';
                        } elseif ($status === \App\Models\Recipient::STATUS_FAILED) {
                            $status = 'bounced';
                        }
                        
                        fputcsv($handle, [
                            $recipient->email,
                            $recipient->name ?? '',
                            $status,
                            $recipient->sent_at ?? '',
                            $recipient->opened_at ?? '',
                            $recipient->open_count,
                            $recipient->error_message ?? ''
                        ]);
                    }

                    // Flush every chunk (5000 rows)
                    if (ob_get_level() > 0) ob_flush();
                    flush();
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Expires' => '0',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Retry all failed recipients.
     */
    public function retryFailed(Campaign $campaign)
    {
        if ((int) $campaign->user_id !== auth()->id()) {
            abort(403);
        }

        // Count failed recipients
        $failedCount = $campaign->recipients()
            ->where('status', Recipient::STATUS_FAILED)
            ->count();

        if ($failedCount === 0) {
            return redirect()->route('campaigns.show', $campaign)
                ->with('error', 'No failed recipients to retry.');
        }

        // Reset failed recipients back to valid status
        $campaign->recipients()
            ->where('status', Recipient::STATUS_FAILED)
            ->update([
                'status' => Recipient::STATUS_VALID,
                'error_message' => null,
            ]);

        // If campaign was completed, set it back to sending
        if ($campaign->status === Campaign::STATUS_COMPLETED) {
            $campaign->update([
                'status' => Campaign::STATUS_SENDING,
                'completed_at' => null,
            ]);
        }

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', "{$failedCount} failed recipients reset and ready to retry!");
    }

    /**
     * Duplicate/clone a campaign as a new draft.
     */
    public function duplicate(Campaign $campaign)
    {
        if ((int) $campaign->user_id !== auth()->id()) {
            abort(403);
        }

        // Create new campaign with same settings
        $newCampaign = Campaign::create([
            'user_id' => auth()->id(),
            'name' => $campaign->name . ' (Copy)',
            'from_name' => $campaign->from_name,
            'reply_to' => $campaign->reply_to,
            'emails_per_hour' => $campaign->emails_per_hour,
            'min_delay_seconds' => $campaign->min_delay_seconds,
            'max_delay_seconds' => $campaign->max_delay_seconds,
            'status' => Campaign::STATUS_DRAFT,
        ]);

        // Copy subject lines
        foreach ($campaign->subjectLines as $subject) {
            $newCampaign->subjectLines()->create([
                'user_id' => auth()->id(),
                'subject' => $subject->subject,
            ]);
        }

        // Copy body templates
        foreach ($campaign->bodyTemplates as $body) {
            $newCampaign->bodyTemplates()->create([
                'user_id' => auth()->id(),
                'name' => $body->name,
                'html_content' => $body->html_content,
                'plain_content' => $body->plain_content,
            ]);
        }

        // Copy contact list associations
        $contactListIds = $campaign->contactLists->pluck('id')->toArray();
        if (!empty($contactListIds)) {
            $newCampaign->contactLists()->attach($contactListIds);
        }

        return redirect()->route('campaigns.show', $newCampaign)
            ->with('success', "Campaign duplicated as \"{$newCampaign->name}\". You can edit it before starting.");
    }
}
