<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index()
    {
        $users = User::orderBy('name')->get();
        return view('users.index', compact('users'));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create()
    {
        return view('users.create');
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'is_admin' => ['boolean'],
            'daily_email_limit' => ['nullable', 'integer', 'min:0'],
            'monthly_email_limit' => ['nullable', 'integer', 'min:0'],
            'yearly_email_limit' => ['nullable', 'integer', 'min:0'],
            'expires_at' => ['nullable', 'date'],
            'manages_own_smtp' => ['boolean'],
            'smtps' => ['exclude_if:manages_own_smtp,1', 'required_if:manages_own_smtp,0', 'array'],
            'smtps.*.name' => ['required_if:manages_own_smtp,0', 'string', 'max:255'],
            'smtps.*.host' => ['required_if:manages_own_smtp,0', 'string', 'max:255'],
            'smtps.*.port' => ['required_if:manages_own_smtp,0', 'integer', 'min:1', 'max:65535'],
            'smtps.*.encryption' => ['required_if:manages_own_smtp,0', \Illuminate\Validation\Rule::in(['tls', 'ssl', 'none'])],
            'smtps.*.from_name' => ['required_if:manages_own_smtp,0', 'string', 'max:255'],
            'smtps.*.username' => ['required_if:manages_own_smtp,0', 'email', 'max:255'],
            'smtps.*.password' => ['required_if:manages_own_smtp,0', 'string'],
            'smtps.*.pacing_strategy' => ['required_if:manages_own_smtp,0', 'string', 'in:per_hour,per_day'],
            'smtps.*.daily_limit' => ['nullable', 'integer', 'min:1'],
            'smtps.*.min_emails_per_hour' => ['nullable', 'integer', 'min:1'],
            'smtps.*.max_emails_per_hour' => ['nullable', 'integer', 'min:1', 'gte:smtps.*.min_emails_per_hour'],
            'smtps.*.min_emails_per_day' => ['nullable', 'integer', 'min:1'],
            'smtps.*.max_emails_per_day' => ['nullable', 'integer', 'min:1', 'gte:smtps.*.min_emails_per_day'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_admin' => $validated['is_admin'] ?? false,
            'manages_own_smtp' => $validated['manages_own_smtp'] ?? true,
            'daily_email_limit' => $validated['daily_email_limit'] ?: null,
            'monthly_email_limit' => $validated['monthly_email_limit'] ?: null,
            'yearly_email_limit' => $validated['yearly_email_limit'] ?: null,
            'expires_at' => $validated['expires_at'] ?: null,
        ]);

        if (isset($validated['manages_own_smtp']) && $validated['manages_own_smtp'] == false && !empty($validated['smtps'])) {
            foreach ($validated['smtps'] as $smtpData) {
                if (empty($smtpData['host'])) continue;
                $password = str_replace(' ', '', $smtpData['password']);
                \App\Models\SmtpConfig::create([
                    'user_id' => $user->id,
                    'name' => $smtpData['name'],
                    'host' => $smtpData['host'],
                    'port' => $smtpData['port'],
                    'encryption' => $smtpData['encryption'],
                    'from_name' => $smtpData['from_name'],
                    'from_email' => $smtpData['username'],
                    'username' => $smtpData['username'],
                    'password' => $password,
                    'is_active' => true,
                    'pacing_strategy' => $smtpData['pacing_strategy'],
                    'daily_limit' => $smtpData['daily_limit'] ?: null,
                    'min_emails_per_hour' => $smtpData['min_emails_per_hour'] ?: null,
                    'max_emails_per_hour' => $smtpData['max_emails_per_hour'] ?: null,
                    'min_emails_per_day' => $smtpData['min_emails_per_day'] ?: null,
                    'max_emails_per_day' => $smtpData['max_emails_per_day'] ?: null,
                ]);
            }
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Show user edit form.
     */
    public function edit(User $user)
    {
        return view('users.edit', compact('user'));
    }

    /**
     * Update user.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'is_admin' => ['boolean'],
            'daily_email_limit' => ['nullable', 'integer', 'min:0'],
            'monthly_email_limit' => ['nullable', 'integer', 'min:0'],
            'yearly_email_limit' => ['nullable', 'integer', 'min:0'],
            'expires_at' => ['nullable', 'date'],
            'manages_own_smtp' => ['boolean'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->is_admin = $validated['is_admin'] ?? false;
        if (isset($validated['manages_own_smtp'])) {
            $user->manages_own_smtp = $validated['manages_own_smtp'];
        }
        $user->daily_email_limit = $validated['daily_email_limit'] ?: null;
        $user->monthly_email_limit = $validated['monthly_email_limit'] ?: null;
        $user->yearly_email_limit = $validated['yearly_email_limit'] ?: null;
        $user->expires_at = $validated['expires_at'] ?: null;
        
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        
        $user->save();

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Delete user.
     */
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete yourself.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }
    
    /**
     * Impersonate a user.
     */
    public function impersonate(User $user)
    {
        if ($user->is_admin) {
            return back()->with('error', 'You cannot impersonate an admin.');
        }

        if (auth()->id() === $user->id) {
            return back()->with('error', 'You are already logged in as this user.');
        }

        session()->put('impersonated_by', auth()->id());
        auth()->login($user);

        return redirect()->route('dashboard')->with('success', "You are now impersonating {$user->name}.");
    }

    /**
     * Leave impersonation.
     */
    public function leaveImpersonation()
    {
        if (!session()->has('impersonated_by')) {
            return redirect()->route('dashboard');
        }

        $adminId = session()->pull('impersonated_by');
        $admin = User::find($adminId);

        if ($admin) {
            auth()->login($admin);
            return redirect()->route('admin.users.index')->with('success', 'You have returned to your admin account.');
        }

        auth()->logout();
        return redirect()->route('login');
    }
}
