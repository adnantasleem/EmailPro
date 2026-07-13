<?php

namespace App\Http\Controllers;

use App\Models\SubjectGroup;
use App\Models\SubjectLine;
use Illuminate\Http\Request;

class SubjectGroupController extends Controller
{
    /**
     * Display a listing of subject groups.
     */
    public function index()
    {
        $groups = SubjectGroup::where('user_id', auth()->id())
            ->withCount('subjectLines')
            ->latest()
            ->get();

        return view('subject-groups.index', compact('groups'));
    }

    /**
     * Store a newly created subject group (from modal).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        SubjectGroup::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
        ]);

        return redirect()->back()
            ->with('success', 'Subject group created.');
    }

    /**
     * Display the specified subject group with all its subjects.
     */
    public function show(SubjectGroup $subjectGroup)
    {
        if ((int) $subjectGroup->user_id !== auth()->id()) {
            abort(403);
        }

        $subjectGroup->load('subjectLines');

        // Get ungrouped subjects for adding to this group
        $ungroupedSubjects = SubjectLine::where('user_id', auth()->id())
            ->whereNull('campaign_id')
            ->whereNull('subject_group_id')
            ->orderBy('subject')
            ->get();

        return view('subject-groups.show', compact('subjectGroup', 'ungroupedSubjects'));
    }

    /**
     * Update the specified subject group name.
     */
    public function update(Request $request, SubjectGroup $subjectGroup)
    {
        if ((int) $subjectGroup->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $subjectGroup->update($validated);

        return redirect()->back()
            ->with('success', 'Group name updated.');
    }

    /**
     * Remove the specified subject group.
     * Subjects inside become ungrouped (FK set null via migration).
     */
    public function destroy(SubjectGroup $subjectGroup)
    {
        if ((int) $subjectGroup->user_id !== auth()->id()) {
            abort(403);
        }

        $subjectGroup->delete();

        return redirect()->route('subject-groups.index')
            ->with('success', 'Group deleted. Subject lines have been ungrouped.');
    }

    /**
     * Add existing subjects to this group.
     */
    public function addSubjects(Request $request, SubjectGroup $subjectGroup)
    {
        if ((int) $subjectGroup->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'subject_ids' => 'required|array|min:1',
            'subject_ids.*' => 'exists:subject_lines,id',
        ]);

        SubjectLine::where('user_id', auth()->id())
            ->whereIn('id', $validated['subject_ids'])
            ->update(['subject_group_id' => $subjectGroup->id]);

        return redirect()->back()
            ->with('success', count($validated['subject_ids']) . ' subject(s) added to group.');
    }

    /**
     * Remove a subject from this group (ungroup, keep in DB).
     */
    public function removeSubject(SubjectGroup $subjectGroup, SubjectLine $subject)
    {
        if ((int) $subjectGroup->user_id !== auth()->id()) {
            abort(403);
        }

        $subject->update(['subject_group_id' => null]);

        return redirect()->back()
            ->with('success', 'Subject removed from group.');
    }

    /**
     * Delete a subject from this group permanently.
     */
    public function deleteSubject(SubjectGroup $subjectGroup, SubjectLine $subject)
    {
        if ((int) $subjectGroup->user_id !== auth()->id()) {
            abort(403);
        }

        $subject->delete();

        return redirect()->back()
            ->with('success', 'Subject deleted permanently.');
    }
}
