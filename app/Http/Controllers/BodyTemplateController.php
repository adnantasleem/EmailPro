<?php

namespace App\Http\Controllers;

use App\Models\BodyTemplate;
use App\Models\BodyGroup;
use Illuminate\Http\Request;

class BodyTemplateController extends Controller
{
    /**
     * Display a listing of body templates.
     */
    public function index()
    {
        $bodyTemplates = BodyTemplate::where('user_id', auth()->id())
            ->whereNull('campaign_id')
            ->with('bodyGroup')
            ->latest()
            ->get();

        return view('bodies.index', compact('bodyTemplates'));
    }

    /**
     * Show the form for creating a new body template.
     */
    public function create()
    {
        $groups = BodyGroup::where('user_id', auth()->id())->orderBy('name')->get();
        return view('bodies.create', compact('groups'));
    }

    /**
     * Store a newly created body template.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'html_content' => 'required|string',
            'plain_content' => 'nullable|string',
            'body_group_id' => 'nullable|exists:body_groups,id',
        ]);

        BodyTemplate::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'html_content' => $validated['html_content'],
            'plain_content' => $validated['plain_content'] ?? null,
            'body_group_id' => $validated['body_group_id'] ?? null,
        ]);

        return redirect()->route('bodies.index')
            ->with('success', 'Body template created.');
    }

    /**
     * Show the specified body template.
     */
    public function show(BodyTemplate $body)
    {
        if ((int) $body->user_id !== auth()->id()) {
            abort(403);
        }

        return view('bodies.show', compact('body'));
    }

    /**
     * Show the form for editing the specified body template.
     */
    public function edit(BodyTemplate $body)
    {
        if ((int) $body->user_id !== auth()->id()) {
            abort(403);
        }

        $groups = BodyGroup::where('user_id', auth()->id())->orderBy('name')->get();
        return view('bodies.edit', compact('body', 'groups'));
    }

    /**
     * Update the specified body template.
     */
    public function update(Request $request, BodyTemplate $body)
    {
        if ((int) $body->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'html_content' => 'required|string',
            'plain_content' => 'nullable|string',
            'body_group_id' => 'nullable|exists:body_groups,id',
        ]);

        $body->update([
            'name' => $validated['name'],
            'html_content' => $validated['html_content'],
            'plain_content' => $validated['plain_content'] ?? null,
            'body_group_id' => $validated['body_group_id'] ?? null,
        ]);

        return redirect()->route('bodies.index')
            ->with('success', 'Body template updated.');
    }

    /**
     * Remove the specified body template.
     */
    public function destroy(BodyTemplate $body)
    {
        if ((int) $body->user_id !== auth()->id()) {
            abort(403);
        }

        $body->delete();

        return redirect()->route('bodies.index')
            ->with('success', 'Body template deleted.');
    }
}
