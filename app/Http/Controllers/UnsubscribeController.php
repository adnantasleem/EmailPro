<?php

namespace App\Http\Controllers;

use App\Models\Unsubscribe;
use Illuminate\Http\Request;

class UnsubscribeController extends Controller
{
    /**
     * Display a listing of unsubscribed emails.
     */
    public function index()
    {
        $unsubscribes = Unsubscribe::where('user_id', auth()->id())
            ->orderBy('unsubscribed_at', 'desc')
            ->paginate(50);

        $totalCount = Unsubscribe::where('user_id', auth()->id())->count();

        return view('unsubscribes.index', compact('unsubscribes', 'totalCount'));
    }

    /**
     * Store a newly unsubscribed email.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'reason' => 'nullable|string|max:255',
        ]);

        Unsubscribe::addEmail(auth()->id(), $validated['email'], $validated['reason'] ?? null);

        return redirect()->route('unsubscribes.index')
            ->with('success', 'Email added to unsubscribe list.');
    }

    /**
     * Import multiple emails to unsubscribe list.
     */
    public function import(Request $request)
    {
        $validated = $request->validate([
            'emails' => 'nullable|string',
            'file' => 'nullable|file|mimes:csv,txt|max:10240',
        ]);

        $result = ['imported' => 0, 'duplicates' => 0];

        if (!empty($validated['emails'])) {
            $result = Unsubscribe::importEmails(auth()->id(), $validated['emails']);
        }

        if ($request->hasFile('file')) {
            $content = file_get_contents($request->file('file')->path());
            $fileResult = Unsubscribe::importEmails(auth()->id(), $content);
            $result['imported'] += $fileResult['imported'];
            $result['duplicates'] += $fileResult['duplicates'];
        }

        $message = "{$result['imported']} emails added to unsubscribe list.";
        if ($result['duplicates'] > 0) {
            $message .= " {$result['duplicates']} already existed.";
        }

        return redirect()->route('unsubscribes.index')
            ->with('success', $message);
    }

    /**
     * Remove an email from unsubscribe list.
     */
    public function destroy(Unsubscribe $unsubscribe)
    {
        if ((int) $unsubscribe->user_id !== auth()->id()) {
            abort(403);
        }

        $unsubscribe->delete();

        return redirect()->route('unsubscribes.index')
            ->with('success', 'Email removed from unsubscribe list.');
    }

    /**
     * Export unsubscribed emails as CSV.
     */
    public function export()
    {
        $unsubscribes = Unsubscribe::where('user_id', auth()->id())->get();

        // Create temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_');
        $handle = fopen($tempFile, 'w');
        fputcsv($handle, ['email', 'reason', 'unsubscribed_at']);
        foreach ($unsubscribes as $unsub) {
            fputcsv($handle, [
                $unsub->email,
                $unsub->reason ?? '',
                $unsub->unsubscribed_at?->format('Y-m-d H:i:s') ?? ''
            ]);
        }
        fclose($handle);

        return response()->download($tempFile, 'unsubscribes.csv', [
            'Content-Type' => 'text/csv',
        ])->deleteFileAfterSend(true);
    }
}
