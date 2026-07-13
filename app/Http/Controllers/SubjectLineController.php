<?php

namespace App\Http\Controllers;

use App\Models\SubjectLine;
use App\Models\SubjectGroup;
use Illuminate\Http\Request;

class SubjectLineController extends Controller
{
    /**
     * Display a listing of subject lines.
     */
    public function index()
    {
        $subjectLines = SubjectLine::where('user_id', auth()->id())
            ->whereNull('campaign_id')
            ->with('subjectGroup')
            ->latest()
            ->get();

        return view('subjects.index', compact('subjectLines'));
    }

    /**
     * Show the form for creating a new subject line.
     */
    public function create()
    {
        $groups = SubjectGroup::where('user_id', auth()->id())->orderBy('name')->get();
        return view('subjects.create', compact('groups'));
    }

    /**
     * Store a newly created subject line.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:500',
            'subject_group_id' => 'nullable|exists:subject_groups,id',
        ]);

        SubjectLine::create([
            'user_id' => auth()->id(),
            'subject' => $validated['subject'],
            'subject_group_id' => $validated['subject_group_id'] ?? null,
        ]);

        return redirect()->route('subjects.index')
            ->with('success', 'Subject line created.');
    }

    /**
     * Show the form for editing the specified subject line.
     */
    public function edit(SubjectLine $subject)
    {
        if ((int) $subject->user_id !== auth()->id()) {
            abort(403);
        }

        $groups = SubjectGroup::where('user_id', auth()->id())->orderBy('name')->get();
        return view('subjects.edit', compact('subject', 'groups'));
    }

    /**
     * Update the specified subject line.
     */
    public function update(Request $request, SubjectLine $subject)
    {
        if ((int) $subject->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'subject' => 'required|string|max:500',
            'subject_group_id' => 'nullable|exists:subject_groups,id',
        ]);

        $subject->update([
            'subject' => $validated['subject'],
            'subject_group_id' => $validated['subject_group_id'] ?? null,
        ]);

        return redirect()->route('subjects.index')
            ->with('success', 'Subject line updated.');
    }

    /**
     * Remove the specified subject line.
     */
    public function destroy(SubjectLine $subject)
    {
        if ((int) $subject->user_id !== auth()->id()) {
            abort(403);
        }

        $subject->delete();

        return redirect()->route('subjects.index')
            ->with('success', 'Subject line deleted.');
    }
}
