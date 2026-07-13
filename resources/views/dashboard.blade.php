<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Dashboard') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">Welcome back! Here's your email marketing overview.</p>
            </div>
            <div class="flex items-center gap-4">
                <!-- Server Clock -->
                <div class="hidden sm:flex items-center gap-2 px-4 py-2 bg-gray-50 rounded-lg border border-gray-200">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <div id="serverClock" class="text-sm font-semibold text-gray-800 tabular-nums">{{ now()->format('h:i:s A') }}</div>
                        <div class="text-xs text-gray-400">Server ({{ config('app.timezone') }})</div>
                    </div>
                </div>
                <a href="{{ route('campaigns.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg font-semibold text-sm text-white hover:opacity-90 shadow-lg transition" style="background-color: #4338CA;">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    New Campaign
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-6 bg-green-50 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded-r-lg shadow-sm">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            <!-- Hero Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Campaigns -->
                <div class="relative overflow-hidden bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl p-6 shadow-lg shadow-indigo-500/20">
                    <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white/10 rounded-full"></div>
                    <div class="absolute bottom-0 left-0 -mb-8 -ml-8 w-32 h-32 bg-white/5 rounded-full"></div>
                    <div class="relative">
                        <div class="flex items-center justify-between">
                            <div class="p-3 bg-white/20 rounded-xl">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4">
                            <p class="text-4xl font-bold text-white">{{ $stats['total_campaigns'] }}</p>
                            <p class="text-indigo-100 text-sm mt-1">Total Campaigns</p>
                        </div>
                    </div>
                </div>

                <!-- Active Campaigns -->
                <div class="relative overflow-hidden bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl p-6 shadow-lg shadow-emerald-500/20">
                    <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white/10 rounded-full"></div>
                    <div class="absolute bottom-0 left-0 -mb-8 -ml-8 w-32 h-32 bg-white/5 rounded-full"></div>
                    <div class="relative">
                        <div class="flex items-center justify-between">
                            <div class="p-3 bg-white/20 rounded-xl">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            @if($stats['active_campaigns'] > 0)
                                <span class="flex h-3 w-3">
                                    <span class="animate-ping absolute inline-flex h-3 w-3 rounded-full bg-white opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-white"></span>
                                </span>
                            @endif
                        </div>
                        <div class="mt-4">
                            <p class="text-4xl font-bold text-white">{{ $stats['active_campaigns'] }}</p>
                            <p class="text-emerald-100 text-sm mt-1">Active Now</p>
                        </div>
                    </div>
                </div>

                <!-- Emails Sent Today -->
                <div class="relative overflow-hidden bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-6 shadow-lg shadow-blue-500/20">
                    <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white/10 rounded-full"></div>
                    <div class="absolute bottom-0 left-0 -mb-8 -ml-8 w-32 h-32 bg-white/5 rounded-full"></div>
                    <div class="relative">
                        <div class="flex items-center justify-between">
                            <div class="p-3 bg-white/20 rounded-xl">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4">
                            <p class="text-4xl font-bold text-white">{{ number_format($stats['emails_sent_today']) }}</p>
                            <p class="text-blue-100 text-sm mt-1">Sent Today</p>
                        </div>
                    </div>
                </div>

                <!-- SMTP Status -->
                <div class="relative overflow-hidden bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl p-6 shadow-lg shadow-purple-500/20">
                    <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white/10 rounded-full"></div>
                    <div class="absolute bottom-0 left-0 -mb-8 -ml-8 w-32 h-32 bg-white/5 rounded-full"></div>
                    <div class="relative">
                        <div class="flex items-center justify-between">
                            <div class="p-3 bg-white/20 rounded-xl">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4">
                            <p class="text-4xl font-bold text-white">{{ $stats['active_smtp'] }}<span class="text-xl text-purple-200">/{{ $stats['total_smtp'] }}</span></p>
                            <p class="text-purple-100 text-sm mt-1">Active SMTP</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Email Quota (if user has limit) -->
            @if(auth()->user()->monthly_email_limit)
            @php
                $user = auth()->user();
                $limit = $user->monthly_email_limit;
                $sent = $user->emails_sent_this_month;
                $remaining = $user->remaining_email_quota;
                $percentage = ($sent / $limit) * 100;
            @endphp
            <div class="mb-8">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-indigo-100 rounded-lg">
                                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Monthly Email Quota</h3>
                                <p class="text-sm text-gray-500">Resets on {{ now()->addMonth()->startOfMonth()->format('F 1, Y') }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($remaining) }}</p>
                            <p class="text-sm text-gray-500">emails remaining</p>
                        </div>
                    </div>
                    <div class="mb-2">
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600">{{ number_format($sent) }} sent</span>
                            <span class="text-gray-600">{{ number_format($limit) }} limit</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div class="h-3 rounded-full transition-all duration-500
                                @if($percentage >= 100) bg-gradient-to-r from-red-500 to-red-600
                                @elseif($percentage >= 80) bg-gradient-to-r from-orange-500 to-orange-600
                                @elseif($percentage >= 50) bg-gradient-to-r from-yellow-500 to-yellow-600
                                @else bg-gradient-to-r from-green-500 to-green-600
                                @endif"
                                 style="width: {{ min($percentage, 100) }}%"></div>
                        </div>
                    </div>
                    @if($percentage >= 80)
                        <p class="text-sm text-orange-600 mt-2">
                            ⚠️ You've used {{ number_format($percentage, 0) }}% of your monthly quota. Contact admin if you need more emails.
                        </p>
                    @endif
                    @if($remaining <= 0)
                        <p class="text-sm text-red-600 mt-2 font-medium">
                            ⛔ You've reached your monthly limit. Email sending is paused until {{ now()->addMonth()->startOfMonth()->format('F 1') }}.
                        </p>
                    @endif
                </div>
            </div>
            @endif

            <!-- Quick Access Modules -->
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Access</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <a href="{{ route('campaigns.index') }}" class="group relative bg-white rounded-xl p-5 shadow-sm border border-gray-100 hover:shadow-lg hover:border-indigo-200 transition-all duration-200">
                        <div class="flex flex-col items-center text-center">
                            <div class="p-3 bg-indigo-50 rounded-xl group-hover:bg-indigo-100 transition">
                                <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </div>
                            <span class="mt-3 text-sm font-medium text-gray-700 group-hover:text-indigo-600">Campaigns</span>
                        </div>
                    </a>
                    <a href="{{ route('smtp.index') }}" class="group relative bg-white rounded-xl p-5 shadow-sm border border-gray-100 hover:shadow-lg hover:border-purple-200 transition-all duration-200">
                        <div class="flex flex-col items-center text-center">
                            <div class="p-3 bg-purple-50 rounded-xl group-hover:bg-purple-100 transition">
                                <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                                </svg>
                            </div>
                            <span class="mt-3 text-sm font-medium text-gray-700 group-hover:text-purple-600">SMTP</span>
                        </div>
                    </a>
                    <a href="{{ route('contact-lists.index') }}" class="group relative bg-white rounded-xl p-5 shadow-sm border border-gray-100 hover:shadow-lg hover:border-emerald-200 transition-all duration-200">
                        <div class="flex flex-col items-center text-center">
                            <div class="p-3 bg-emerald-50 rounded-xl group-hover:bg-emerald-100 transition">
                                <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <span class="mt-3 text-sm font-medium text-gray-700 group-hover:text-emerald-600">Contacts</span>
                        </div>
                    </a>
                    <a href="{{ route('subjects.index') }}" class="group relative bg-white rounded-xl p-5 shadow-sm border border-gray-100 hover:shadow-lg hover:border-amber-200 transition-all duration-200">
                        <div class="flex flex-col items-center text-center">
                            <div class="p-3 bg-amber-50 rounded-xl group-hover:bg-amber-100 transition">
                                <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                </svg>
                            </div>
                            <span class="mt-3 text-sm font-medium text-gray-700 group-hover:text-amber-600">Subjects</span>
                        </div>
                    </a>
                    <a href="{{ route('bodies.index') }}" class="group relative bg-white rounded-xl p-5 shadow-sm border border-gray-100 hover:shadow-lg hover:border-blue-200 transition-all duration-200">
                        <div class="flex flex-col items-center text-center">
                            <div class="p-3 bg-blue-50 rounded-xl group-hover:bg-blue-100 transition">
                                <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <span class="mt-3 text-sm font-medium text-gray-700 group-hover:text-blue-600">Bodies</span>
                        </div>
                    </a>
                    <a href="{{ route('unsubscribes.index') }}" class="group relative bg-white rounded-xl p-5 shadow-sm border border-gray-100 hover:shadow-lg hover:border-rose-200 transition-all duration-200">
                        <div class="flex flex-col items-center text-center">
                            <div class="p-3 bg-rose-50 rounded-xl group-hover:bg-rose-100 transition">
                                <svg class="w-7 h-7 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                            </div>
                            <span class="mt-3 text-sm font-medium text-gray-700 group-hover:text-rose-600">Unsubscribes</span>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Active Campaigns Status -->
            @if($activeCampaigns->count() > 0)
            <div class="mb-8">
                <div class="bg-white rounded-2xl shadow-sm border border-blue-100 overflow-hidden">
                    <div class="p-5 border-b border-blue-50 bg-gradient-to-r from-blue-50 to-indigo-50">
                        <div class="flex items-center gap-3">
                            <div class="flex h-3 w-3">
                                <span class="animate-ping absolute inline-flex h-3 w-3 rounded-full bg-blue-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-blue-500"></span>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Campaigns Running Now</h3>
                        </div>
                    </div>
                    <div class="divide-y divide-gray-50">
                        @foreach($activeCampaigns as $ac)
                            <a href="{{ route('campaigns.show', $ac['id']) }}" class="block p-5 hover:bg-gray-50 transition">
                                <div class="flex justify-between items-center mb-2">
                                    <div class="flex items-center gap-3">
                                        <h4 class="font-medium text-gray-900">{{ $ac['name'] }}</h4>
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $ac['status'] === 'sending' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700' }}">
                                            {{ ucfirst($ac['status']) }}
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <span class="text-green-600 font-semibold">{{ $ac['sent'] }}</span>
                                        / {{ $ac['total'] }} sent
                                        @if($ac['failed'] > 0)
                                            · <span class="text-red-500">{{ $ac['failed'] }} failed</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2.5">
                                    <div class="bg-gradient-to-r from-blue-500 to-indigo-500 h-2.5 rounded-full transition-all duration-500" style="width: {{ $ac['progress'] }}%"></div>
                                </div>
                                <div class="flex justify-between mt-1.5">
                                    <span class="text-xs text-gray-500">{{ $ac['progress'] }}% complete</span>
                                    @if($ac['started_at'])
                                        <span class="text-xs text-gray-400">Started {{ $ac['started_at'] }}</span>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- SMTP Alerts: Paused + Exhausted -->
            @if($pausedSmtps->count() > 0 || $exhaustedSmtps->count() > 0)
            <div class="mb-8 space-y-4">
                @foreach($pausedSmtps as $pSmtp)
                <div class="bg-white rounded-2xl shadow-sm border border-red-100 p-5">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start gap-3">
                            <div class="p-2 bg-red-100 rounded-lg mt-0.5">
                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.834-1.964-.834-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="font-semibold text-red-900">⚠️ SMTP "{{ $pSmtp['name'] }}" — Auto-Paused</h4>
                                <p class="text-sm text-red-700 mt-1">{{ $pSmtp['pause_reason'] }}</p>
                                <p class="text-xs text-gray-500 mt-1">Paused at: {{ $pSmtp['paused_at'] }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            @if($pSmtp['is_past'])
                                <span class="px-3 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700">Resuming soon...</span>
                            @else
                                <div class="text-sm font-medium text-gray-900">Auto-resumes at</div>
                                <div class="text-lg font-bold text-indigo-600" data-countdown="{{ $pSmtp['resume_at_iso'] }}">{{ $pSmtp['resume_at'] }}</div>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach

                @foreach($exhaustedSmtps as $eSmtp)
                <div class="bg-white rounded-2xl shadow-sm border border-amber-100 p-5">
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-amber-100 rounded-lg mt-0.5">
                            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="font-semibold text-amber-900">📊 SMTP "{{ $eSmtp['name'] }}" — Daily Quota Reached</h4>
                            <p class="text-sm text-amber-700 mt-1">Sent {{ $eSmtp['sent_today'] }}/{{ $eSmtp['daily_limit'] }} emails today. Resets automatically at <strong>midnight</strong>.</p>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Recent Campaigns - Takes 2 columns -->
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-6 border-b border-gray-100">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Recent Campaigns</h3>
                                <p class="text-sm text-gray-500">Your latest email campaigns</p>
                            </div>
                            <a href="{{ route('campaigns.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">View All →</a>
                        </div>
                    </div>
                    <div class="divide-y divide-gray-50">
                        @forelse($recentCampaigns as $campaign)
                            <a href="{{ route('campaigns.show', $campaign['id']) }}" class="block p-5 hover:bg-gray-50 transition">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3">
                                            <h4 class="font-medium text-gray-900">{{ $campaign['name'] }}</h4>
                                            <span class="px-2.5 py-0.5 text-xs font-medium rounded-full
                                                @if($campaign['status'] === 'draft') bg-gray-100 text-gray-600
                                                @elseif($campaign['status'] === 'validating') bg-yellow-100 text-yellow-700
                                                @elseif($campaign['status'] === 'sending') bg-blue-100 text-blue-700
                                                @elseif($campaign['status'] === 'paused') bg-orange-100 text-orange-700
                                                @else bg-green-100 text-green-700
                                                @endif">
                                                {{ ucfirst($campaign['status']) }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-500 mt-1">{{ $campaign['created_at'] }}</p>
                                    </div>
                                    <div class="text-right">
                                        <div class="flex items-center gap-4 text-sm">
                                            <span class="text-gray-500">{{ $campaign['stats']['total'] }} total</span>
                                            <span class="text-green-600 font-medium">{{ $campaign['stats']['sent'] }} sent</span>
                                        </div>
                                        @if($campaign['stats']['total'] > 0)
                                            <div class="mt-2 w-32 bg-gray-100 rounded-full h-1.5">
                                                @php $pct = ($campaign['stats']['sent'] / $campaign['stats']['total']) * 100; @endphp
                                                <div class="bg-gradient-to-r from-indigo-500 to-purple-500 h-1.5 rounded-full" style="width: {{ $pct }}%"></div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="p-12 text-center">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                </div>
                                <h4 class="text-gray-900 font-medium mb-1">No campaigns yet</h4>
                                <p class="text-gray-500 text-sm mb-4">Create your first email campaign to get started</p>
                                <a href="{{ route('campaigns.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                                    Create Campaign
                                </a>
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- SMTP Status - Takes 1 column -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-6 border-b border-gray-100">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">SMTP Servers</h3>
                                <p class="text-sm text-gray-500">Daily sending status</p>
                            </div>
                            <a href="{{ route('smtp.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">Manage →</a>
                        </div>
                    </div>
                    <div class="p-4 space-y-4">
                        @forelse($smtpStatus as $smtp)
                            <div class="p-4 bg-gray-50 rounded-xl">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="font-medium text-gray-900 text-sm">{{ $smtp['name'] }}</span>
                                    <span class="text-xs text-gray-500">{{ $smtp['sent_today'] }}/{{ $smtp['daily_limit'] }}</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    @php
                                        $percentage = $smtp['daily_limit'] > 0 ? ($smtp['sent_today'] / $smtp['daily_limit']) * 100 : 0;
                                    @endphp
                                    <div class="h-2 rounded-full transition-all duration-500
                                        @if($percentage > 90) bg-gradient-to-r from-red-500 to-red-600
                                        @elseif($percentage > 70) bg-gradient-to-r from-amber-500 to-amber-600
                                        @else bg-gradient-to-r from-emerald-500 to-emerald-600
                                        @endif"
                                         style="width: {{ min($percentage, 100) }}%"></div>
                                </div>
                                <p class="mt-2 text-xs text-gray-500">{{ $smtp['remaining'] }} emails remaining</p>
                            </div>
                        @empty
                            <div class="p-8 text-center">
                                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2" />
                                    </svg>
                                </div>
                                <p class="text-gray-500 text-sm mb-3">No SMTP servers</p>
                                <a href="{{ route('smtp.create') }}" class="text-indigo-600 text-sm font-medium hover:underline">Add SMTP →</a>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Server Clock Script (always runs) -->
    <script>
        // Live server clock - synced to server time at page load
        (function() {
            const serverTimeAtLoad = new Date("{{ now()->toIso8601String() }}");
            const clientTimeAtLoad = new Date();
            const offset = serverTimeAtLoad - clientTimeAtLoad;

            function updateClock() {
                const serverNow = new Date(Date.now() + offset);
                let hours = serverNow.getHours();
                const minutes = String(serverNow.getMinutes()).padStart(2, '0');
                const seconds = String(serverNow.getSeconds()).padStart(2, '0');
                const ampm = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12;
                hours = hours ? hours : 12;
                const hoursStr = String(hours).padStart(2, '0');

                const clockEl = document.getElementById('serverClock');
                if (clockEl) {
                    clockEl.textContent = hoursStr + ':' + minutes + ':' + seconds + ' ' + ampm;
                }
            }

            updateClock();
            setInterval(updateClock, 1000);
        })();
    </script>

    @if($activeCampaigns->count() > 0 || $pausedSmtps->count() > 0)
    <script>
        // Live countdown for paused SMTP resume times
        document.querySelectorAll('[data-countdown]').forEach(function(el) {
            const resumeAt = new Date(el.dataset.countdown);
            
            function updateCountdown() {
                const now = new Date();
                const diff = resumeAt - now;
                
                if (diff <= 0) {
                    el.innerHTML = '<span class="text-green-600">Resuming now...</span>';
                    setTimeout(() => location.reload(), 5000);
                    return;
                }
                
                const hours = Math.floor(diff / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                
                let timeStr = '';
                if (hours > 0) timeStr += hours + 'h ';
                timeStr += minutes + 'm ' + seconds + 's';
                
                el.textContent = timeStr;
            }
            
            updateCountdown();
            setInterval(updateCountdown, 1000);
        });

        // Auto-refresh dashboard every 60 seconds when campaigns are active
        @if($activeCampaigns->count() > 0)
        setTimeout(() => location.reload(), 60000);
        @endif
    </script>
    @endif
</x-app-layout>
