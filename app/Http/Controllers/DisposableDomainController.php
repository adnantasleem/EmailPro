<?php

namespace App\Http\Controllers;

use App\Models\DisposableDomain;
use Illuminate\Http\Request;

class DisposableDomainController extends Controller
{
    /**
     * Display a listing of disposable domains.
     */
    public function index(Request $request)
    {
        $query = DisposableDomain::where('user_id', auth()->id())->orderBy('domain');

        if ($request->filled('search')) {
            $query->where('domain', 'like', '%' . $request->search . '%');
        }

        $domains = $query->paginate(50);

        return view('disposable-domains.index', compact('domains'));
    }

    /**
     * Store a newly created disposable domain.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'domain' => 'required|string|max:255',
        ]);

        // Clean the domain
        $domain = strtolower(trim($validated['domain']));
        
        // Remove @ if user added it
        if (str_starts_with($domain, '@')) {
            $domain = substr($domain, 1);
        }

        // Check if already exists for this user
        if (DisposableDomain::where('user_id', auth()->id())->where('domain', $domain)->exists()) {
            return redirect()->route('disposable-domains.index')
                ->with('error', "Domain '{$domain}' is already in your blocklist.");
        }

        DisposableDomain::create([
            'user_id' => auth()->id(),
            'domain' => $domain,
        ]);

        return redirect()->route('disposable-domains.index')
            ->with('success', "Domain '{$domain}' added to blocklist.");
    }

    /**
     * Import multiple domains at once.
     */
    public function import(Request $request)
    {
        $request->validate([
            'domains' => 'required|string',
        ]);

        $lines = preg_split('/\r\n|\r|\n/', $request->domains);
        $added = 0;
        $skipped = 0;

        foreach ($lines as $line) {
            $domain = strtolower(trim($line));
            
            // Skip empty lines
            if (empty($domain)) {
                continue;
            }

            // Remove @ if present
            if (str_starts_with($domain, '@')) {
                $domain = substr($domain, 1);
            }

            // Check if already exists for this user
            if (DisposableDomain::where('user_id', auth()->id())->where('domain', $domain)->exists()) {
                $skipped++;
                continue;
            }

            DisposableDomain::create([
                'user_id' => auth()->id(),
                'domain' => $domain,
            ]);
            $added++;
        }

        return redirect()->route('disposable-domains.index')
            ->with('success', "Added {$added} domains. Skipped {$skipped} duplicates.");
    }

    /**
     * Remove the specified disposable domain.
     */
    public function destroy(DisposableDomain $disposableDomain)
    {
        // Check ownership - only allow deleting user's own domains
        if ((int) $disposableDomain->user_id !== auth()->id()) {
            abort(403, 'You can only delete your own blocked domains.');
        }

        $domain = $disposableDomain->domain;
        $disposableDomain->delete();

        return redirect()->route('disposable-domains.index')
            ->with('success', "Domain '{$domain}' removed from blocklist.");
    }

    /**
     * Remove all disposable domains for current user.
     */
    public function destroyAll()
    {
        $count = DisposableDomain::where('user_id', auth()->id())->count();
        DisposableDomain::where('user_id', auth()->id())->delete();

        return redirect()->route('disposable-domains.index')
            ->with('success', "Removed all {$count} domains from your blocklist.");
    }
}
