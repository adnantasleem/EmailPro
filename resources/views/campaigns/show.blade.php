<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $campaign->name }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">Created {{ $campaign->created_at->format('M d, Y H:i') }}</p>
            </div>
            <div class="flex items-center gap-2">
                @if($campaign->import_status === 'importing')
                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800 flex items-center gap-1">
                        <svg class="animate-spin h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Importing Contacts...
                    </span>
                @endif
                <span class="px-3 py-1 text-sm font-semibold rounded-full
                    @if($campaign->status === 'draft') bg-gray-100 text-gray-800
                    @elseif($campaign->status === 'validating') bg-yellow-100 text-yellow-800
                    @elseif($campaign->status === 'sending') bg-blue-100 text-blue-800
                    @elseif($campaign->status === 'paused') bg-orange-100 text-orange-800
                    @else bg-green-100 text-green-800
                    @endif">
                    {{ ucfirst($campaign->status) }}
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Action Buttons -->
            <div class="mb-6 flex flex-wrap gap-3">
                <button type="button" onclick="document.getElementById('testEmailModal').classList.remove('hidden')" class="px-4 py-2 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200 text-sm font-medium">
                    📧 Send Test Email
                </button>
                @if($campaign->status === 'draft')
                    <form action="{{ route('campaigns.start', $campaign) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm font-medium">
                            ▶ Start Campaign
                        </button>
                    </form>
                    <a href="{{ route('campaigns.edit', $campaign) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 text-sm font-medium">
                        Edit Settings
                    </a>
                @elseif($campaign->status === 'validating')
                    <span class="px-4 py-2 bg-yellow-100 text-yellow-800 rounded-md text-sm font-medium">
                        ⏳ Validating emails...
                    </span>
                    <form action="{{ route('campaigns.stop', $campaign) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-red-100 text-red-700 rounded-md hover:bg-red-200 text-sm font-medium" onclick="return confirm('Stop and reset this campaign?')">
                            Stop
                        </button>
                    </form>
                @elseif($campaign->status === 'sending')
                    <form action="{{ route('campaigns.pause', $campaign) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded-md hover:bg-yellow-600 text-sm font-medium">
                            ⏸ Pause
                        </button>
                    </form>
                    <form action="{{ route('campaigns.stop', $campaign) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-red-100 text-red-700 rounded-md hover:bg-red-200 text-sm font-medium" onclick="return confirm('Stop and reset this campaign?')">
                            Stop
                        </button>
                    </form>
                @elseif($campaign->status === 'paused')
                    <form action="{{ route('campaigns.resume', $campaign) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-medium">
                            ▶ Resume
                        </button>
                    </form>
                    <form action="{{ route('campaigns.stop', $campaign) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-red-100 text-red-700 rounded-md hover:bg-red-200 text-sm font-medium" onclick="return confirm('Stop and reset this campaign?')">
                            Stop & Reset
                        </button>
                    </form>
                @elseif($campaign->status === 'completed')
                    <form action="{{ route('campaigns.restart', $campaign) }}" method="POST" class="inline" onsubmit="return confirm('Restart this campaign? This will reset all recipients to allow re-sending.')">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm font-medium">
                            🔄 Restart Campaign
                        </button>
                    </form>
                    <a href="{{ route('campaigns.edit', $campaign) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 text-sm font-medium">
                        Edit Settings
                    </a>
                @endif
                <a href="{{ route('recipients.index', $campaign) }}" class="px-4 py-2 bg-indigo-100 text-indigo-700 rounded-md hover:bg-indigo-200 text-sm font-medium">
                    View Recipients
                </a>
                <a href="{{ route('campaigns.export', $campaign) }}" class="px-4 py-2 bg-green-100 text-green-700 rounded-md hover:bg-green-200 text-sm font-medium">
                    📥 Export CSV
                </a>
                <a href="{{ route('campaigns.report', $campaign) }}" target="_blank" class="px-4 py-2 bg-pink-100 text-pink-700 rounded-md hover:bg-pink-200 text-sm font-medium">
                    📊 View Full Report
                </a>
                <form action="{{ route('campaigns.duplicate', $campaign) }}" method="POST" class="inline" onsubmit="return confirm('Duplicate this campaign as a new draft?')">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-purple-100 text-purple-700 rounded-md hover:bg-purple-200 text-sm font-medium">
                        📋 Duplicate
                    </button>
                </form>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4 mb-6">
                <div class="bg-white p-4 rounded-lg shadow-sm text-center">
                    <div class="text-2xl font-bold text-gray-900">{{ $stats['total'] }}</div>
                    <div class="text-xs text-gray-500">Total</div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-sm text-center">
                    <div class="text-2xl font-bold text-yellow-600">{{ $stats['pending'] }}</div>
                    <div class="text-xs text-gray-500">Pending</div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-sm text-center">
                    <div class="text-2xl font-bold text-blue-600">{{ $stats['validating'] }}</div>
                    <div class="text-xs text-gray-500">Validating</div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-sm text-center">
                    <div class="text-2xl font-bold text-green-600">{{ $stats['valid'] }}</div>
                    <div class="text-xs text-gray-500">Valid</div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-sm text-center">
                    <div class="text-2xl font-bold text-red-600">{{ $stats['invalid'] }}</div>
                    <div class="text-xs text-gray-500">Invalid</div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-sm text-center">
                    <div class="text-2xl font-bold text-indigo-600">{{ $stats['sent'] }}</div>
                    <div class="text-xs text-gray-500">Sent</div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-sm text-center">
                    <div class="text-2xl font-bold text-orange-600">{{ $stats['failed'] }}</div>
                    <div class="text-xs text-gray-500">Failed</div>
                    @if($stats['failed'] > 0)
                    <form action="{{ route('campaigns.retry-failed', $campaign) }}" method="POST" class="mt-2" onsubmit="return confirm('Retry all {{ $stats['failed'] }} failed emails?')">
                        @csrf
                        <button type="submit" class="text-xs px-2 py-1 bg-orange-600 text-white rounded hover:bg-orange-700">
                            🔄 Retry
                        </button>
                    </form>
                    @endif
                </div>
                <div class="bg-white p-4 rounded-lg shadow-sm text-center border-2 border-purple-200">
                    <div class="text-2xl font-bold text-purple-600">{{ $stats['opened'] ?? 0 }}</div>
                    <div class="text-xs text-gray-500">👁️ Opened</div>
                    @if(($stats['sent'] ?? 0) > 0)
                        <div class="text-xs text-purple-500 mt-1">{{ number_format((($stats['opened'] ?? 0) / $stats['sent']) * 100, 1) }}% rate</div>
                    @endif
                </div>
            </div>

            <!-- Progress Bar -->
            @php
                $total = $stats['total'];
                $sent = $stats['sent'];
                $failed = $stats['failed'];
                $pct = $total > 0 ? (($sent + $failed) / $total) * 100 : 0;
            @endphp
            <div class="bg-white p-4 rounded-lg shadow-sm mb-6">
                <div class="flex justify-between text-sm text-gray-600 mb-2">
                    <span>Overall Progress</span>
                    <span>{{ number_format($pct, 1) }}% Complete ({{ $sent }} sent, {{ $failed }} failed of {{ $total }})</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div class="h-3 rounded-full bg-indigo-500" style="width: {{ $pct }}%"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Subject Line Performance -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Subject Line Performance</h3>
                    </div>
                    <div class="divide-y divide-gray-200">
                        @forelse($subjectStats as $subject)
                            <div class="p-4">
                                <div class="flex justify-between items-start">
                                    <p class="text-sm text-gray-900 flex-1 pr-4">{{ $subject['subject'] }}</p>
                                    <span class="text-sm font-medium text-indigo-600">{{ $subject['usage_count'] }} uses</span>
                                </div>
                            </div>
                        @empty
                            <div class="p-4 text-center text-gray-500">No subject lines</div>
                        @endforelse
                    </div>
                </div>

                <!-- Body Template Performance -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Body Template Performance</h3>
                    </div>
                    <div class="divide-y divide-gray-200">
                        @forelse($bodyStats as $index => $body)
                            <div class="p-4">
                                <div class="flex justify-between items-start">
                                    <p class="text-sm text-gray-600 flex-1 pr-4"><span class="font-semibold text-gray-900">{{ $body['name'] ?? 'Template #' . ($index + 1) }}</span>: {{ $body['preview'] }}</p>
                                    <span class="text-sm font-medium text-indigo-600">{{ $body['usage_count'] }} uses</span>
                                </div>
                            </div>
                        @empty
                            <div class="p-4 text-center text-gray-500">No body templates</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Recent Email Logs -->
            <div class="mt-6 bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Email Logs</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Recipient</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject Used</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">SMTP</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sent At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($recentLogs as $log)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $log->recipient->email }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ Str::limit($log->subjectLine->subject, 40) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $log->smtpConfig->name }}</td>
                                    <td class="px-4 py-3">
                                        @if($log->status === 'sent')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Sent</span>
                                        @else
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Failed</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $log->sent_at?->format('M d, H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">No emails sent yet</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Failed Recipients -->
            @if($failedRecipients->count() > 0)
                @php
                    // Categorize errors for summary
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
                @endphp
                <div class="mt-6 bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="p-4 border-b border-red-200 bg-gradient-to-r from-red-50 to-orange-50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-xl">⚠️</span>
                                <h3 class="text-lg font-semibold text-red-900">Failed Recipients</h3>
                                <span class="px-2.5 py-0.5 text-sm font-medium rounded-full bg-red-100 text-red-800">{{ $failedRecipients->count() }}</span>
                            </div>
                        </div>
                        <!-- Error Category Summary -->
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach($errorCategories as $category => $count)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full
                                    @if($category === 'Connection Timeout') bg-yellow-100 text-yellow-800
                                    @elseif($category === 'Connection Closed') bg-orange-100 text-orange-800
                                    @elseif($category === 'DNS Resolution Failed') bg-purple-100 text-purple-800
                                    @elseif($category === 'Sending Limit / Quota') bg-blue-100 text-blue-800
                                    @elseif($category === 'No SMTP Available') bg-gray-100 text-gray-800
                                    @elseif($category === 'Connection Refused') bg-pink-100 text-pink-800
                                    @else bg-gray-100 text-gray-700
                                    @endif
                                ">
                                    @if($category === 'Connection Timeout') ⏱️
                                    @elseif($category === 'Connection Closed') 🔌
                                    @elseif($category === 'DNS Resolution Failed') 🌐
                                    @elseif($category === 'Sending Limit / Quota') 📊
                                    @elseif($category === 'No SMTP Available') 📭
                                    @elseif($category === 'Connection Refused') 🚫
                                    @else ❓
                                    @endif
                                    {{ $category }}: {{ $count }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-8">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Error Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($failedRecipients as $index => $recipient)
                                    @php
                                        $msg = $recipient->error_message ?? 'Unknown error';
                                        if (str_contains($msg, 'timed out') || str_contains($msg, 'Connection timed out')) {
                                            $errorType = 'Timeout';
                                            $errorIcon = '⏱️';
                                            $errorClass = 'bg-yellow-50 text-yellow-800';
                                            $badgeClass = 'bg-yellow-100 text-yellow-700';
                                        } elseif (str_contains($msg, 'closed unexpectedly')) {
                                            $errorType = 'Closed';
                                            $errorIcon = '🔌';
                                            $errorClass = 'bg-orange-50 text-orange-800';
                                            $badgeClass = 'bg-orange-100 text-orange-700';
                                        } elseif (str_contains($msg, 'Name or service not known') || str_contains($msg, 'getaddrinfo')) {
                                            $errorType = 'DNS Error';
                                            $errorIcon = '🌐';
                                            $errorClass = 'bg-purple-50 text-purple-800';
                                            $badgeClass = 'bg-purple-100 text-purple-700';
                                        } elseif (str_contains($msg, 'sending limit') || str_contains($msg, 'quota') || str_contains($msg, '550')) {
                                            $errorType = 'Quota';
                                            $errorIcon = '📊';
                                            $errorClass = 'bg-blue-50 text-blue-800';
                                            $badgeClass = 'bg-blue-100 text-blue-700';
                                        } elseif (str_contains($msg, 'No available SMTP')) {
                                            $errorType = 'No SMTP';
                                            $errorIcon = '📭';
                                            $errorClass = 'bg-gray-50 text-gray-800';
                                            $badgeClass = 'bg-gray-200 text-gray-700';
                                        } elseif (str_contains($msg, 'refused')) {
                                            $errorType = 'Refused';
                                            $errorIcon = '🚫';
                                            $errorClass = 'bg-pink-50 text-pink-800';
                                            $badgeClass = 'bg-pink-100 text-pink-700';
                                        } else {
                                            $errorType = 'Error';
                                            $errorIcon = '❌';
                                            $errorClass = 'bg-red-50 text-red-800';
                                            $badgeClass = 'bg-red-100 text-red-700';
                                        }
                                        // Create a short readable error message
                                        $shortMsg = Str::limit($msg, 80);
                                    @endphp
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-4 py-3 text-xs text-gray-400 font-mono">{{ $index + 1 }}</td>
                                        <td class="px-4 py-3">
                                            <span class="text-sm font-medium text-gray-900">{{ $recipient->email }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold rounded-full {{ $badgeClass }}">
                                                {{ $errorIcon }} {{ $errorType }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div x-data="{ expanded: false }" class="max-w-lg">
                                                <p class="text-xs text-gray-600 leading-relaxed" x-show="!expanded">
                                                    {{ $shortMsg }}
                                                    @if(strlen($msg) > 80)
                                                        <button @click="expanded = true" class="ml-1 text-indigo-600 hover:text-indigo-800 font-medium underline">show more</button>
                                                    @endif
                                                </p>
                                                <div x-show="expanded" x-cloak>
                                                    <p class="text-xs text-gray-600 leading-relaxed bg-gray-50 p-2 rounded border border-gray-200 break-all">{{ $msg }}</p>
                                                    <button @click="expanded = false" class="mt-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium underline">show less</button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Who Opened Emails -->
            @if($openedRecipients->count() > 0)
                <div class="mt-6 bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="p-4 border-b border-gray-200 bg-purple-50">
                        <h3 class="text-lg font-semibold text-purple-900">👁️ Who Opened ({{ $openedRecipients->count() }})</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">First Opened</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Times Opened</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($openedRecipients as $recipient)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ $recipient->email }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $recipient->name ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $recipient->opened_at->format('M d, H:i') }}</td>
                                        <td class="px-4 py-3 text-sm text-purple-600 font-medium">{{ $recipient->open_count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Campaign Settings -->
            <div class="mt-6 bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Campaign Settings</h3>
                <dl class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <dt class="text-sm text-gray-500">Scheduled At</dt>
                        <dd class="text-lg font-medium text-gray-900">{{ $campaign->scheduled_at?->format('M d, Y H:i') ?? 'Immediate' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Started At</dt>
                        <dd class="text-lg font-medium text-gray-900">{{ $campaign->started_at?->format('M d, Y H:i') ?? '-' }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Danger Zone -->
            @if(in_array($campaign->status, ['draft', 'completed', 'paused']))
                <div class="mt-6 bg-white rounded-lg shadow-sm p-6 border border-red-200">
                    <h3 class="text-lg font-semibold text-red-900 mb-4">Danger Zone</h3>
                    <form action="{{ route('campaigns.destroy', $campaign) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this campaign? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm font-medium">
                            Delete Campaign
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>

    {{-- Auto-refresh when campaign is actively processing --}}
    @if(in_array($campaign->status, ['sending', 'validating']) || $stats['pending'] > 0 || $stats['validating'] > 0 || $campaign->import_status === 'importing')
    <script>
        // Auto-refresh every 60 seconds
        let countdown = 60;
        const countdownEl = document.createElement('div');
        countdownEl.className = 'fixed bottom-4 right-4 bg-blue-600 text-white px-4 py-2 rounded-lg shadow-lg text-sm z-50';
        countdownEl.innerHTML = '🔄 Auto-refresh in <span id="countdown">60</span>s';
        document.body.appendChild(countdownEl);

        const countdownSpan = document.getElementById('countdown');
        setInterval(() => {
            countdown--;
            countdownSpan.textContent = countdown;
            if (countdown <= 0) {
                location.reload();
            }
        }, 1000);
    </script>
    @endif

    <!-- Test Email Modal -->
    <div id="testEmailModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-data="{ 
        selectedSubject: '{{ $campaign->subjectLines->first()?->id ?? '' }}',
        selectedBody: '{{ $campaign->bodyTemplates->first()?->id ?? '' }}',
        isLoading: false,
        message: '',
        isError: false,
        openSmtp: false,
        searchSmtp: '',
        selectedSmtp: '{{ $campaign->smtpConfigs->first()?->id ?? '' }}',
        smtps: [
            @foreach($smtpConfigs as $smtp)
            { id: '{{ $smtp->id }}', name: '{{ addslashes($smtp->name) }}' },
            @endforeach
        ],
        get filteredSmtps() {
            if (this.searchSmtp === '') return this.smtps;
            return this.smtps.filter(s => s.name.toLowerCase().includes(this.searchSmtp.toLowerCase()));
        },
        get selectedSmtpName() {
            let selected = this.smtps.find(s => s.id == this.selectedSmtp);
            return selected ? selected.name : 'Select SMTP...';
        },
        submitTestEmail(e) {
            this.isLoading = true;
            this.message = '';
            
            let formData = new FormData(e.target);
            formData.set('smtp_id', this.selectedSmtp);
            
            fetch(e.target.action, {
                method: 'POST',
                body: formData,
                headers: { 
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json().then(data => ({status: res.status, body: data})))
            .then(res => {
                this.isLoading = false;
                if (res.status === 200 && res.body.success) {
                    this.message = res.body.message;
                    this.isError = false;
                    setTimeout(() => {
                        document.getElementById('testEmailModal').classList.add('hidden');
                        this.message = '';
                    }, 2000);
                } else {
                    this.message = res.body.message || 'Failed to send test email.';
                    this.isError = true;
                }
            })
            .catch(err => {
                this.isLoading = false;
                this.message = 'An error occurred while sending.';
                this.isError = true;
            });
        }
    }">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-900 bg-opacity-75 backdrop-blur-sm transition-opacity" aria-hidden="true" onclick="document.getElementById('testEmailModal').classList.add('hidden')"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            
            <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <form action="{{ route('campaigns.test-email', $campaign) }}" method="POST" @submit.prevent="submitTestEmail">
                    @csrf
                    <input type="hidden" name="subject_id" x-model="selectedSubject">
                    <input type="hidden" name="body_id" x-model="selectedBody">

                    <!-- Header -->
                    <div class="bg-white px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900" id="modal-title">Send Test Email</h3>
                            <p class="mt-1 text-sm text-gray-500">Preview exactly how your campaign will look in a real inbox.</p>
                        </div>
                        <button type="button" onclick="document.getElementById('testEmailModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-500 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <!-- Alert Message -->
                    <div x-show="message" x-transition class="px-6 pt-4">
                        <div :class="isError ? 'bg-red-50 text-red-700 border-red-200' : 'bg-green-50 text-green-700 border-green-200'" class="p-3 rounded-md border text-sm flex items-center gap-2">
                            <svg x-show="!isError" class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <svg x-show="isError" class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span x-text="message"></span>
                        </div>
                    </div>

                    <div class="px-6 py-5 space-y-6">
                        <!-- Recipient Info -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Send to Address</label>
                                <input type="email" name="test_email" required class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            
                            <div class="relative">
                                <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Account</label>
                                <div class="relative" @click.away="openSmtp = false">
                                    <button type="button" @click="openSmtp = !openSmtp" class="relative w-full bg-white border border-gray-300 rounded-md shadow-sm pl-3 pr-10 py-2 text-left cursor-default focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        <span class="block truncate" x-text="selectedSmtpName"></span>
                                        <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                            <svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.76 9.2a.75.75 0 011.06.04l2.7 2.908 2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.04-1.06z" clip-rule="evenodd" /></svg>
                                        </span>
                                    </button>

                                    <div x-show="openSmtp" style="display: none;" class="absolute z-10 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                        <div class="px-2 pb-2 sticky top-0 bg-white">
                                            <input type="text" x-model="searchSmtp" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Search SMTP...">
                                        </div>
                                        <ul tabindex="-1" role="listbox">
                                            <template x-for="smtp in filteredSmtps" :key="smtp.id">
                                                <li @click="selectedSmtp = smtp.id; openSmtp = false; searchSmtp = ''" class="text-gray-900 cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-indigo-600 hover:text-white" role="option">
                                                    <span class="block truncate" :class="selectedSmtp == smtp.id ? 'font-semibold' : 'font-normal'" x-text="smtp.name"></span>
                                                    <span x-show="selectedSmtp == smtp.id" class="text-indigo-600 absolute inset-y-0 right-0 flex items-center pr-4">
                                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                                    </span>
                                                </li>
                                            </template>
                                            <li x-show="filteredSmtps.length === 0" class="text-gray-500 cursor-default select-none relative py-2 pl-3 pr-9">No SMTPs found</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Variables -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">First Name (Mock Data)</label>
                                <input type="text" name="first_name" placeholder="e.g. John" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Last Name (Mock Data)</label>
                                <input type="text" name="last_name" placeholder="e.g. Doe" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                        </div>

                        <!-- Content Selection -->
                        <div class="border-t border-gray-100 pt-5">
                            <h4 class="text-sm font-medium text-gray-900 mb-4">Email Content to Test</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <!-- Subject -->
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Subject Line</label>
                                    <div class="max-h-48 overflow-y-auto space-y-2 pr-1 custom-scrollbar">
                                        @foreach($campaign->subjectLines as $subject)
                                            <div @click="selectedSubject = '{{ $subject->id }}'" 
                                                 class="cursor-pointer border rounded-md p-2.5 transition-all duration-150"
                                                 :class="selectedSubject === '{{ $subject->id }}' ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500' : 'border-gray-200 hover:border-indigo-300'">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-3.5 h-3.5 rounded-full border flex-shrink-0 flex items-center justify-center" :class="selectedSubject === '{{ $subject->id }}' ? 'border-indigo-600' : 'border-gray-300'">
                                                        <div class="w-1.5 h-1.5 rounded-full bg-indigo-600" x-show="selectedSubject === '{{ $subject->id }}'"></div>
                                                    </div>
                                                    <span class="text-sm text-gray-800 line-clamp-2">{{ $subject->subject }}</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Body -->
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Body Template</label>
                                    <div class="max-h-48 overflow-y-auto space-y-2 pr-1 custom-scrollbar">
                                        @foreach($campaign->bodyTemplates as $index => $body)
                                            <div @click="selectedBody = '{{ $body->id }}'" 
                                                 class="cursor-pointer border rounded-md p-2.5 transition-all duration-150"
                                                 :class="selectedBody === '{{ $body->id }}' ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500' : 'border-gray-200 hover:border-indigo-300'">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-3.5 h-3.5 rounded-full border flex-shrink-0 flex items-center justify-center" :class="selectedBody === '{{ $body->id }}' ? 'border-indigo-600' : 'border-gray-300'">
                                                        <div class="w-1.5 h-1.5 rounded-full bg-indigo-600" x-show="selectedBody === '{{ $body->id }}'"></div>
                                                    </div>
                                                    <span class="text-sm font-medium text-gray-900">{{ $body->name ?? 'Template #' . ($index + 1) }}</span>
                                                </div>
                                                <div class="mt-1.5 ml-5 text-xs text-gray-500 line-clamp-1">
                                                    {{ $body->plain_content ? Str::limit($body->plain_content, 50) : 'HTML Template' }}
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 flex items-center justify-end gap-3 rounded-b-xl">
                        <button type="button" onclick="document.getElementById('testEmailModal').classList.add('hidden')" class="px-4 py-2 bg-white border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancel
                        </button>
                        <button type="submit" :disabled="isLoading" class="px-4 py-2 bg-indigo-600 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 flex items-center gap-2 disabled:opacity-75 disabled:cursor-not-allowed">
                            <svg x-show="!isLoading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                            <svg x-show="isLoading" style="display:none;" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span x-text="isLoading ? 'Sending...' : 'Send Test Email'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</x-app-layout>
