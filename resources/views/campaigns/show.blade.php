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
                                    <p class="text-sm text-gray-600 flex-1 pr-4">Template #{{ $index + 1 }}: {{ $body['preview'] }}</p>
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
                        <dt class="text-sm text-gray-500">Emails Per Hour</dt>
                        <dd class="text-lg font-medium text-gray-900">{{ $campaign->emails_per_hour }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Delay Range</dt>
                        <dd class="text-lg font-medium text-gray-900">{{ $campaign->min_delay_seconds }}s - {{ $campaign->max_delay_seconds }}s</dd>
                    </div>
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
</x-app-layout>
