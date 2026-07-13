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
            </div>

            <!-- Recipients Table -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <!-- Search & Actions Bar -->
                <div class="p-4 border-b border-gray-200 flex flex-wrap gap-4 items-center justify-between">
                    <form action="{{ route('recipients.index', $campaign) }}" method="GET" class="flex gap-2 flex-1 max-w-md">
                        <input type="hidden" name="status" value="{{ request('status') }}">
                        <x-text-input name="search" type="text" class="flex-1" placeholder="Search by email..." :value="request('search')" />
                        <x-primary-button>Search</x-primary-button>
                    </form>
                    <div class="flex gap-2">
                        <a href="{{ route('recipients.export', ['campaign' => $campaign, 'status' => request('status', 'all')]) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 text-sm font-medium">
                            📥 Export CSV
                        </a>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sent At</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($recipients as $recipient)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $recipient->email }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $recipient->name ?? '-' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full
                                            @if($recipient->status === 'pending') bg-yellow-100 text-yellow-800
                                            @elseif($recipient->status === 'valid') bg-green-100 text-green-800
                                            @elseif(in_array($recipient->status, ['invalid', 'disposable'])) bg-red-100 text-red-800
                                            @elseif($recipient->status === 'sent') bg-indigo-100 text-indigo-800
                                            @elseif($recipient->status === 'failed') bg-orange-100 text-orange-800
                                            @else bg-gray-100 text-gray-800
                                            @endif">
                                            {{ ucfirst($recipient->status) }}
                                        </span>
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
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">No recipients found</td>
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
