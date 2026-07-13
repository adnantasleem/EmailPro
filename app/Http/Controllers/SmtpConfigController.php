<?php

namespace App\Http\Controllers;

use App\Models\SmtpConfig;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SmtpConfigController extends Controller
{
    /**
     * Display a listing of SMTP configs.
     */
    public function index()
    {
        $smtpConfigs = SmtpConfig::where('user_id', auth()->id())
            ->latest()
            ->get()
            ->map(function ($smtp) {
            return [
                'id' => $smtp->id,
                'name' => $smtp->name,
                'host' => $smtp->host,
                'port' => $smtp->port,
                'from_email' => $smtp->from_email,
                'from_name' => $smtp->from_name,
                'encryption' => $smtp->encryption,
                'daily_limit' => $smtp->daily_limit,
                'sent_today' => $smtp->sent_today,
                'remaining' => $smtp->remaining_today,
                'is_active' => $smtp->is_active,
                // Warmup fields
                'is_warming_up' => $smtp->is_warming_up,
                'warmup_day' => $smtp->warmup_day,
                'warmup_daily_limit' => $smtp->warmup_daily_limit,
                // Bounce tracking fields
                'bounce_rate' => $smtp->bounce_rate,
                'total_sent' => $smtp->total_sent,
                'total_bounced' => $smtp->total_bounced,
                'auto_paused' => $smtp->auto_paused,
                'paused_at' => $smtp->paused_at,
                'pause_reason' => $smtp->pause_reason,
                'created_at' => $smtp->created_at->format('M d, Y'),
            ];
        });

        return view('smtp.index', compact('smtpConfigs'));
    }

    /**
     * Show the form for creating a new SMTP config.
     */
    public function create()
    {
        return view('smtp.create');
    }

    /**
     * Store a newly created SMTP config.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('smtp_configs')->where(fn ($query) => $query->where('user_id', auth()->id()))
            ],
            'password' => 'required|string',
            'encryption' => ['required', Rule::in(['tls', 'ssl', 'none'])],
            'from_email' => 'required|email|max:255',
            'from_name' => 'required|string|max:255',
            'daily_limit' => 'required|integer|min:1|max:100000',
            'min_emails_per_hour' => 'nullable|integer|min:1|max:100000',
            'max_emails_per_hour' => 'nullable|integer|min:1|max:100000|gte:min_emails_per_hour',
            'active_time_start' => 'nullable|date_format:H:i',
            'active_time_end' => 'nullable|date_format:H:i|required_with:active_time_start',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $validated['user_id'] = auth()->id();

        // Remove spaces from password (for App Passwords like "abcd efgh ijkl mnop")
        if (!empty($validated['password'])) {
            $validated['password'] = str_replace(' ', '', $validated['password']);
        }

        SmtpConfig::create($validated);

        return redirect()->route('smtp.index')
            ->with('success', 'SMTP configuration created successfully.');
    }

    /**
     * Show the form for editing the specified SMTP config.
     */
    public function edit(SmtpConfig $smtp)
    {
        if ((int) $smtp->user_id !== auth()->id()) {
            abort(403);
        }
        return view('smtp.edit', compact('smtp'));
    }

    /**
     * Update the specified SMTP config.
     */
    public function update(Request $request, SmtpConfig $smtp)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('smtp_configs')->where(fn ($query) => $query->where('user_id', auth()->id()))
                    ->ignore($smtp->id)
            ],
            'password' => 'nullable|string',
            'encryption' => ['required', Rule::in(['tls', 'ssl', 'none'])],
            'from_email' => 'required|email|max:255',
            'from_name' => 'required|string|max:255',
            'daily_limit' => 'required|integer|min:1|max:100000',
            'min_emails_per_hour' => 'nullable|integer|min:1|max:100000',
            'max_emails_per_hour' => 'nullable|integer|min:1|max:100000|gte:min_emails_per_hour',
            'active_time_start' => 'nullable|date_format:H:i',
            'active_time_end' => 'nullable|date_format:H:i|required_with:active_time_start',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        // Only update password if provided
        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            // Remove spaces from password (for App Passwords)
            $validated['password'] = str_replace(' ', '', $validated['password']);
        }

        $smtp->update($validated);

        return redirect()->route('smtp.index')
            ->with('success', 'SMTP configuration updated successfully.');
    }

    /**
     * Remove the specified SMTP config.
     */
    public function destroy(SmtpConfig $smtp)
    {
        if ((int) $smtp->user_id !== auth()->id()) {
            abort(403);
        }
        $smtp->delete();

        return redirect()->route('smtp.index')
            ->with('success', 'SMTP configuration deleted successfully.');
    }

    /**
     * Toggle the active status of an SMTP config.
     */
    public function toggle(SmtpConfig $smtp)
    {
        if ((int) $smtp->user_id !== auth()->id()) {
            abort(403);
        }
        $smtp->update(['is_active' => !$smtp->is_active]);

        return redirect()->route('smtp.index')
            ->with('success', 'SMTP status updated successfully.');
    }

    /**
     * Reset the daily counter for an SMTP config.
     */
    public function resetCounter(SmtpConfig $smtp)
    {
        if ((int) $smtp->user_id !== auth()->id()) {
            abort(403);
        }
        $smtp->update([
            'sent_today' => 0,
            'last_reset_date' => today(),
        ]);

        return redirect()->route('smtp.index')
            ->with('success', 'SMTP counter reset successfully.');
    }

    /**
     * Start warmup mode for an SMTP config.
     */
    public function startWarmup(SmtpConfig $smtp)
    {
        if ((int) $smtp->user_id !== auth()->id()) {
            abort(403);
        }
        
        $smtp->startWarmup();

        return redirect()->route('smtp.index')
            ->with('success', "Warmup started for {$smtp->name}. Daily limit will gradually increase over 28 days.");
    }

    /**
     * End warmup mode for an SMTP config.
     */
    public function endWarmup(SmtpConfig $smtp)
    {
        if ((int) $smtp->user_id !== auth()->id()) {
            abort(403);
        }
        
        $smtp->endWarmup();

        return redirect()->route('smtp.index')
            ->with('success', "Warmup ended for {$smtp->name}. Now using full daily limit: {$smtp->daily_limit}.");
    }

    /**
     * Resume an auto-paused SMTP config.
     */
    public function resume(SmtpConfig $smtp)
    {
        if ((int) $smtp->user_id !== auth()->id()) {
            abort(403);
        }
        
        $smtp->resume();

        return redirect()->route('smtp.index')
            ->with('success', "SMTP {$smtp->name} has been resumed. Bounce counters have been reset.");
    }

    /**
     * Test SMTP connection.
     */
    public function testConnection(Request $request)
    {
        $validated = $request->validate([
            'host' => 'required|string',
            'port' => 'required|integer',
            'username' => 'required|string',
            'password' => 'required|string',
            'encryption' => 'required|string',
            'from_email' => 'required|email',
            'from_name' => 'required|string',
        ]);

        // Remove spaces from password
        $password = str_replace(' ', '', $validated['password']);
        $encryption = strtolower($validated['encryption']);

        try {
            // Build DSN
            if ($encryption === 'ssl') {
                $dsn = sprintf(
                    'smtps://%s:%s@%s:%d',
                    urlencode($validated['username']),
                    urlencode($password),
                    $validated['host'],
                    $validated['port']
                );
            } else {
                $dsn = sprintf(
                    'smtp://%s:%s@%s:%d',
                    urlencode($validated['username']),
                    urlencode($password),
                    $validated['host'],
                    $validated['port']
                );
            }

            $transport = \Symfony\Component\Mailer\Transport::fromDsn($dsn);
            $mailer = new \Symfony\Component\Mailer\Mailer($transport);

            // Send test email
            $email = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address($validated['from_email'], $validated['from_name']))
                ->to($validated['from_email'])
                ->subject('SMTP Test - Connection Successful!')
                ->text('This is a test email from your Email Marketing App. Your SMTP configuration is working correctly!')
                ->html('<h2>SMTP Test Successful!</h2><p>This is a test email from your Email Marketing App.</p><p>Your SMTP configuration is working correctly!</p>');

            $mailer->send($email);

            return response()->json([
                'success' => true,
                'message' => 'Connection successful! Test email sent to ' . $validated['from_email'],
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("SMTP Connection Test failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Connection failed. Please verify your settings and try again.',
            ], 422);
        }
    }

    /**
     * Send a test email using saved SMTP config.
     */
    public function test(SmtpConfig $smtp)
    {
        // Ensure user owns this SMTP config
        if ((int) $smtp->user_id !== auth()->id()) {
            abort(403);
        }

        try {
            // Use decrypted_password accessor (same as EmailSenderService)
            $password = $smtp->decrypted_password;
            $encryption = strtolower($smtp->encryption);

            // Build DSN
            if ($encryption === 'ssl') {
                $dsn = sprintf(
                    'smtps://%s:%s@%s:%d',
                    urlencode($smtp->username),
                    urlencode($password),
                    $smtp->host,
                    $smtp->port
                );
            } else {
                $dsn = sprintf(
                    'smtp://%s:%s@%s:%d',
                    urlencode($smtp->username),
                    urlencode($password),
                    $smtp->host,
                    $smtp->port
                );
            }

            $transport = \Symfony\Component\Mailer\Transport::fromDsn($dsn);
            $mailer = new \Symfony\Component\Mailer\Mailer($transport);

            // Send test email to the from_email address
            $email = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address($smtp->from_email, $smtp->from_name))
                ->to($smtp->from_email)
                ->subject('SMTP Test - ' . $smtp->name)
                ->text('This is a test email from your Email Marketing App. Your SMTP "' . $smtp->name . '" is working correctly!')
                ->html('<h2>SMTP Test Successful!</h2><p>This is a test email from your Email Marketing App.</p><p>Your SMTP <strong>"' . $smtp->name . '"</strong> is working correctly!</p><p>Sent at: ' . now()->format('Y-m-d H:i:s') . '</p>');

            $mailer->send($email);

            return redirect()->route('smtp.index')
                ->with('success', "Test email sent successfully to {$smtp->from_email}!");

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("SMTP Test failed for {$smtp->name}: " . $e->getMessage());
            return redirect()->route('smtp.index')
                ->with('error', "Test failed for {$smtp->name}. Please check your SMTP settings and try again.");
        }
    }
}
