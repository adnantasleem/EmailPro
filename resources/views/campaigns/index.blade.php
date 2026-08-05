<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Campaigns') }}
            </h2>
            <a href="{{ route('campaigns.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 transition" style="background-color: #4338CA;">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Campaign
            </a>
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

            <!-- Search Form -->
            <div class="mb-6 bg-white p-4 rounded-lg shadow-sm">
                <form action="{{ route('campaigns.index') }}" method="GET" class="flex items-center space-x-4">
                    <div class="flex-grow">
                        <label for="search" class="sr-only">Search</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                            <input type="text" name="search" id="search" value="{{ request('search') }}" class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 sm:text-sm border-gray-300 rounded-md" placeholder="Search campaigns...">
                        </div>
                    </div>
                    <div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Search
                        </button>
                    </div>
                    @if(request('search'))
                    <div>
                        <a href="{{ route('campaigns.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Clear
                        </a>
                    </div>
                    @endif
                </form>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="divide-y divide-gray-200">
                    @forelse($campaigns as $campaign)
                        <div class="p-6 hover:bg-gray-50">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <a href="{{ route('campaigns.show', $campaign['id']) }}" class="text-lg font-semibold text-gray-900 hover:text-indigo-600">
                                            {{ $campaign['name'] }}
                                        </a>
                                        <span class="ml-3 px-2.5 py-0.5 text-xs font-semibold rounded-full
                                            @if($campaign['status'] === 'draft') bg-gray-100 text-gray-800
                                            @elseif($campaign['status'] === 'validating') bg-yellow-100 text-yellow-800
                                            @elseif($campaign['status'] === 'sending') bg-blue-100 text-blue-800
                                            @elseif($campaign['status'] === 'paused') bg-orange-100 text-orange-800
                                            @else bg-green-100 text-green-800
                                            @endif">
                                            {{ ucfirst($campaign['status']) }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500">
                                        Created {{ $campaign['created_at'] }}
                                        @if($campaign['scheduled_at'])
                                            · Scheduled for {{ $campaign['scheduled_at'] }}
                                        @endif
                                    </p>

                                    <!-- Progress Bar -->
                                    @php
                                        $total = $campaign['stats']['total'];
                                        $sent = $campaign['stats']['sent'];
                                        $failed = $campaign['stats']['failed'];
                                        $progress = $total > 0 ? (($sent + $failed) / $total) * 100 : 0;
                                    @endphp
                                    <div class="mt-3">
                                        <div class="flex justify-between text-xs text-gray-500 mb-1">
                                            <span>Progress</span>
                                            <span>{{ $sent + $failed }} / {{ $total }} ({{ $sent }} sent, {{ $failed }} failed)</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="h-2 rounded-full bg-indigo-500" style="width: {{ $progress }}%"></div>
                                        </div>
                                    </div>

                                    <div class="mt-3 flex items-center space-x-6 text-sm text-gray-500">
                                        <span>📧 {{ $total }} recipients</span>
                                        <span>✅ {{ $campaign['stats']['valid'] }} valid</span>
                                        <span>❌ {{ $campaign['stats']['invalid'] }} invalid</span>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center space-x-2 ml-4">
                                    <a href="{{ route('campaigns.show', $campaign['id']) }}" class="px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 rounded-md">View</a>
                                    @if($campaign['status'] === 'draft')
                                        <form action="{{ route('campaigns.start', $campaign['id']) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 text-sm bg-green-100 hover:bg-green-200 text-green-700 rounded-md">Start</button>
                                        </form>
                                    @elseif($campaign['status'] === 'sending')
                                        <form action="{{ route('campaigns.pause', $campaign['id']) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 text-sm bg-yellow-100 hover:bg-yellow-200 text-yellow-700 rounded-md">Pause</button>
                                        </form>
                                    @elseif($campaign['status'] === 'paused')
                                        <form action="{{ route('campaigns.resume', $campaign['id']) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 text-sm bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-md">Resume</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-12 text-center text-gray-500">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No campaigns</h3>
                            <p class="mt-1 text-sm text-gray-500">Get started by creating a new campaign.</p>
                            <div class="mt-6">
                                <a href="{{ route('campaigns.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white hover:opacity-90 transition" style="background-color: #4338CA;">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    Create Campaign
                                </a>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>

            @if($campaigns->hasPages())
                <div class="mt-6">
                    {{ $campaigns->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
