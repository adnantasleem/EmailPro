<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('SMTP Servers') }}
            </h2>
            <a href="{{ route('smtp.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 transition" style="background-color: #4338CA;">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add SMTP
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-50 border-l-4 border-green-500 p-4 rounded-md shadow-sm">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-green-800">
                                Success
                            </h3>
                            <div class="mt-2 text-sm text-green-700">
                                <p>{{ session('success') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-50 border-l-4 border-red-500 p-4 rounded-md shadow-sm">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <!-- Heroicon name: solid/x-circle -->
                            <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">
                                Error
                            </h3>
                            <div class="mt-2 text-sm text-red-700 break-words">
                                <p>{{ session('error') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Host</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usage Today</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bounce Rate</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($smtpConfigs as $smtp)
                                <tr class="{{ $smtp['auto_paused'] ? 'bg-red-50' : '' }}">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium text-gray-900">{{ $smtp['name'] }}</div>
                                        @if($smtp['is_warming_up'])
                                            <div class="flex items-center mt-1">
                                                <span class="text-orange-500 mr-1">🔥</span>
                                                <span class="text-xs text-orange-600">Warmup Day {{ $smtp['warmup_day'] }}/28</span>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $smtp['host'] }}:{{ $smtp['port'] }}</div>
                                        <div class="text-sm text-gray-500">{{ strtoupper($smtp['encryption']) }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $smtp['from_name'] }}</div>
                                        <div class="text-sm text-gray-500">{{ $smtp['from_email'] }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-1">
                                                @php
                                                    $effectiveLimit = $smtp['is_warming_up'] ? $smtp['warmup_daily_limit'] : $smtp['daily_limit'];
                                                    $pct = $effectiveLimit > 0 ? ($smtp['sent_today'] / $effectiveLimit) * 100 : 0;
                                                @endphp
                                                <div class="text-sm text-gray-900">
                                                    {{ $smtp['sent_today'] }} / {{ $effectiveLimit }}
                                                    @if($smtp['is_warming_up'])
                                                        <span class="text-xs text-gray-500">(warmup)</span>
                                                    @endif
                                                </div>
                                                <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                                                    <div class="h-2 rounded-full @if($pct > 90) bg-red-500 @elseif($pct > 70) bg-yellow-500 @else bg-green-500 @endif" style="width: {{ min($pct, 100) }}%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @php
                                            $bounceRate = $smtp['bounce_rate'] ?? 0;
                                        @endphp
                                        <div class="text-sm @if($bounceRate > 5) text-red-600 font-medium @elseif($bounceRate > 2) text-yellow-600 @else text-green-600 @endif">
                                            {{ number_format($bounceRate, 1) }}%
                                        </div>
                                        @if($smtp['total_sent'] > 0)
                                            <div class="text-xs text-gray-500">
                                                {{ $smtp['total_bounced'] ?? 0 }}/{{ $smtp['total_sent'] }} all-time
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($smtp['auto_paused'])
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                ⚠️ Auto-Paused
                                            </span>
                                            @if($smtp['paused_at'])
                                                @php
                                                    $resumeAt = \Carbon\Carbon::parse($smtp['paused_at'])->addHours(24);
                                                    $now = now();
                                                    if ($resumeAt->isFuture()) {
                                                        $hoursLeft = $now->diffInHours($resumeAt);
                                                        $minutesLeft = $now->diffInMinutes($resumeAt) % 60;
                                                        $resumeIn = $hoursLeft > 0 ? "{$hoursLeft}h {$minutesLeft}m" : "{$minutesLeft}m";
                                                    } else {
                                                        $resumeIn = 'soon';
                                                    }
                                                @endphp
                                                <div class="text-xs text-orange-600 mt-1 font-medium">🔄 Auto-resumes in {{ $resumeIn }}</div>
                                            @endif
                                            @if($smtp['pause_reason'])
                                                <div class="text-xs text-red-600 mt-1">{{ $smtp['pause_reason'] }}</div>
                                            @endif
                                        @elseif($smtp['is_warming_up'])
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                🔥 Warming Up
                                            </span>
                                        @elseif($smtp['is_active'])
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end space-x-2">
                                            @if($smtp['auto_paused'])
                                                <form action="{{ route('smtp.resume', $smtp['id']) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="text-green-600 hover:text-green-900 font-medium">Resume</button>
                                                </form>
                                            @else
                                                @if($smtp['is_warming_up'])
                                                    <form action="{{ route('smtp.end-warmup', $smtp['id']) }}" method="POST" class="inline">
                                                        @csrf
                                                        <button type="submit" class="text-orange-600 hover:text-orange-900">End Warmup</button>
                                                    </form>
                                                @else
                                                    <form action="{{ route('smtp.start-warmup', $smtp['id']) }}" method="POST" class="inline">
                                                        @csrf
                                                        <button type="submit" class="text-orange-600 hover:text-orange-900">Warmup</button>
                                                    </form>
                                                @endif
                                                <form action="{{ route('smtp.toggle', $smtp['id']) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="text-yellow-600 hover:text-yellow-900">
                                                        {{ $smtp['is_active'] ? 'Disable' : 'Enable' }}
                                                    </button>
                                                </form>
                                            @endif
                                            <form action="{{ route('smtp.test', $smtp['id']) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="text-purple-600 hover:text-purple-900">Test</button>
                                            </form>
                                            <a href="{{ route('smtp.edit', $smtp['id']) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                            <form action="{{ route('smtp.reset-counter', $smtp['id']) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="text-blue-600 hover:text-blue-900">Reset</button>
                                            </form>
                                            <form action="{{ route('smtp.destroy', $smtp['id']) }}" method="POST" class="inline" onsubmit="return confirm('Delete this SMTP?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                        No SMTP servers configured. <a href="{{ route('smtp.create') }}" class="text-indigo-600 hover:underline">Add your first SMTP server</a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Legend -->
            <div class="mt-4 bg-white p-4 rounded-lg shadow-sm">
                <h3 class="text-sm font-medium text-gray-700 mb-2">Status Legend</h3>
                <div class="flex flex-wrap gap-4 text-xs">
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-green-500 rounded-full mr-1"></span>
                        <span>Active</span>
                    </div>
                    <div class="flex items-center">
                        <span class="mr-1">🔥</span>
                        <span>Warming Up (gradual limit increase over 28 days)</span>
                    </div>
                    <div class="flex items-center">
                        <span class="mr-1">⚠️</span>
                        <span>Auto-Paused (bounce rate > 5%, auto-resumes after 24 hours)</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-gray-400 rounded-full mr-1"></span>
                        <span>Inactive</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
