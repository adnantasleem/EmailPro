<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Recipients: {{ $campaign->name }}
                </h2>
            </div>
            <a href="{{ route('campaigns.show', $campaign) }}" class="text-indigo-600 hover:text-indigo-900 text-sm">← Back to Campaign</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Status Tabs -->
            <div class="mb-6 flex flex-wrap gap-2">
                <a href="{{ route('recipients.index', $campaign) }}" 
                   class="px-4 py-2 rounded-md text-sm font-medium {{ !request('status') || request('status') === 'all' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    All ({{ $statusCounts['all'] }})
                </a>
                <a href="{{ route('recipients.index', ['campaign' => $campaign, 'status' => 'pending']) }}" 
                   class="px-4 py-2 rounded-md text-sm font-medium {{ request('status') === 'pending' ? 'bg-yellow-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    Pending ({{ $statusCounts['pending'] }})
                </a>
                <a href="{{ route('recipients.index', ['campaign' => $campaign, 'status' => 'valid']) }}" 
                   class="px-4 py-2 rounded-md text-sm font-medium {{ request('status') === 'valid' ? 'bg-green-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    Valid ({{ $statusCounts['valid'] }})
                </a>
                <a href="{{ route('recipients.index', ['campaign' => $campaign, 'status' => 'invalid']) }}" 
                   class="px-4 py-2 rounded-md text-sm font-medium {{ request('status') === 'invalid' ? 'bg-red-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    Invalid ({{ $statusCounts['invalid'] }})
                </a>
                <a href="{{ route('recipients.index', ['campaign' => $campaign, 'status' => 'sent']) }}" 
                   class="px-4 py-2 rounded-md text-sm font-medium {{ request('status') === 'sent' ? 'bg-indigo-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    Sent ({{ $statusCounts['sent'] }})
                </a>
                <a href="{{ route('recipients.index', ['campaign' => $campaign, 'status' => 'failed']) }}" 
                   class="px-4 py-2 rounded-md text-sm font-medium {{ request('status') === 'failed' ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    Failed ({{ $statusCounts['failed'] }})
                </a>
                <a href="{{ route('recipients.index', ['campaign' => $campaign, 'status' => 'bounced']) }}" 
                   class="px-4 py-2 rounded-md text-sm font-medium {{ request('status') === 'bounced' ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    Bounced ({{ $statusCounts['bounced'] ?? 0 }})
                </a>
            </div>

            <!-- Recipients Table -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <!-- Search & Actions Bar -->
                <div class="p-4 border-b border-gray-200 flex flex-wrap gap-4 items-center justify-between">
                    <form action="{{ route('recipients.index', $campaign) }}" method="GET" class="flex flex-wrap gap-2 flex-1 max-w-2xl items-center">
                        <input type="hidden" name="status" value="{{ request('status') }}">
                        <x-text-input name="search" type="text" class="flex-1 min-w-[180px]" placeholder="Search by email..." :value="request('search')" />
                        @if(isset($campaignSmtps) && $campaignSmtps->count() > 0)
                            <select name="smtp_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" onchange="this.form.submit()">
                                <option value="">All SMTP Accounts</option>
                                @foreach($campaignSmtps as $s)
                                    <option value="{{ $s->id }}" {{ request('smtp_id') == $s->id ? 'selected' : '' }}>
                                        SMTP: {{ $s->name }} ({{ $s->from_email }})
                                    </option>
                                @endforeach
                            </select>
                        @endif
                        <x-primary-button>Filter</x-primary-button>
                        @if(request('search') || request('smtp_id'))
                            <a href="{{ route('recipients.index', ['campaign' => $campaign, 'status' => request('status')]) }}" class="px-3 py-2 text-gray-500 hover:text-gray-700 text-sm">
                                Reset
                            </a>
                        @endif
                    </form>
                    <div class="flex gap-2">
                        <a href="{{ route('recipients.export', ['campaign' => $campaign, 'status' => request('status', 'all'), 'smtp_id' => request('smtp_id')]) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 text-sm font-medium flex items-center gap-1.5 shadow-sm">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            Export CSV
                        </a>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SMTP Used</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sent / Attempted</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($recipients as $recipient)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900 font-medium">{{ $recipient->email }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $recipient->name ?? '-' }}</td>
                                    <td class="px-4 py-3">
                                        @if($recipient->isBounced())
                                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-purple-100 text-purple-800" title="{{ $recipient->error_message }}">
                                                Bounced
                                            </span>
                                            @if($recipient->error_message)
                                                <p class="text-[11px] text-gray-500 mt-0.5 max-w-xs truncate" title="{{ $recipient->error_message }}">{{ $recipient->error_message }}</p>
                                            @endif
                                        @elseif($recipient->status === 'pending')
                                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                        @elseif($recipient->status === 'valid')
                                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800">Valid</span>
                                        @elseif(in_array($recipient->status, ['invalid', 'disposable']))
                                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-800">Invalid</span>
                                        @elseif($recipient->status === 'sent')
                                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800">Sent</span>
                                        @elseif($recipient->status === 'failed')
                                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-orange-100 text-orange-800" title="{{ $recipient->error_message }}">Failed</span>
                                            @if($recipient->error_message)
                                                <p class="text-[11px] text-gray-500 mt-0.5 max-w-xs truncate" title="{{ $recipient->error_message }}">{{ $recipient->error_message }}</p>
                                            @endif
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">{{ ucfirst($recipient->status) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        @php $smtp = $recipient->smtp_account; @endphp
                                        @if($smtp)
                                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-indigo-50 border border-indigo-100 text-xs font-medium text-indigo-900" title="{{ $smtp->host }} ({{ $smtp->from_email }})">
                                                <svg class="w-3.5 h-3.5 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                                <span class="truncate max-w-[150px]">{{ $smtp->name }}</span>
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400 italic">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $recipient->sent_at?->format('M d, H:i') ?? '-' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <form action="{{ route('recipients.destroy', [$campaign, $recipient]) }}" method="POST" class="inline" onsubmit="return confirm('Delete this recipient?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900 text-sm">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">No recipients found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="p-4 border-t border-gray-200">
                    {{ $recipients->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
