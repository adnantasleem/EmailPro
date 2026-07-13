<?php

namespace App\Http\Controllers;

use App\Models\BodyGroup;
use App\Models\BodyTemplate;
use Illuminate\Http\Request;

class BodyGroupController extends Controller
{
    /**
     * Display a listing of body groups.
     */
    public function index()
    {
        $groups = BodyGroup::where('user_id', auth()->id())
            ->withCount('bodyTemplates')
            ->latest()
            ->get();

        return view('body-groups.index', compact('groups'));
    }

    /**
     * Store a newly created body group (from modal).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        BodyGroup::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
        ]);

        return redirect()->back()
            ->with('success', 'Body group created.');
    }

    /**
     * Display the specified body group with all its templates.
     */
    public function show(BodyGroup $bodyGroup)
    {
        if ((int) $bodyGroup->user_id !== auth()->id()) {
            abort(403);
        }

        $bodyGroup->load('bodyTemplates');

        // Get ungrouped body templates for adding to this group
        $ungroupedBodies = BodyTemplate::where('user_id', auth()->id())
            ->whereNull('campaign_id')
            ->whereNull('body_group_id')
            ->orderBy('name')
            ->get();

        return view('body-groups.show', compact('bodyGroup', 'ungroupedBodies'));
    }

    /**
     * Update the specified body group name.
     */
    public function update(Request $request, BodyGroup $bodyGroup)
    {
        if ((int) $bodyGroup->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $bodyGroup->update($validated);

        return redirect()->back()
            ->with('success', 'Group name updated.');
    }

    /**
     * Remove the specified body group.
     * Templates inside become ungrouped (FK set null via migration).
     */
    public function destroy(BodyGroup $bodyGroup)
    {
        if ((int) $bodyGroup->user_id !== auth()->id()) {
            abort(403);
        }

        $bodyGroup->delete();

        return redirect()->route('body-groups.index')
            ->with('success', 'Group deleted. Body templates have been ungrouped.');
    }

    /**
     * Add existing body templates to this group.
     */
    public function addBodies(Request $request, BodyGroup $bodyGroup)
    {
        if ((int) $bodyGroup->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'body_ids' => 'required|array|min:1',
            'body_ids.*' => 'exists:body_templates,id',
        ]);

        BodyTemplate::where('user_id', auth()->id())
            ->whereIn('id', $validated['body_ids'])
            ->update(['body_group_id' => $bodyGroup->id]);

        return redirect()->back()
            ->with('success', count($validated['body_ids']) . ' template(s) added to group.');
    }

    /**
     * Remove a body template from this group (ungroup, keep in DB).
     */
    public function removeBody(BodyGroup $bodyGroup, BodyTemplate $body)
    {
        if ((int) $bodyGroup->user_id !== auth()->id()) {
            abort(403);
        }

        $body->update(['body_group_id' => null]);

        return redirect()->back()
            ->with('success', 'Template removed from group.');
    }

    /**
     * Delete a body template from this group permanently.
     */
    public function deleteBody(BodyGroup $bodyGroup, BodyTemplate $body)
    {
        if ((int) $bodyGroup->user_id !== auth()->id()) {
            abort(403);
        }

        $body->delete();

        return redirect()->back()
            ->with('success', 'Template deleted permanently.');
    }
}
