<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Campaign Report - {{ $campaign->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background-color: white !important; }
            .print-shadow-none { box-shadow: none !important; border: 1px solid #e5e7eb; }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased p-8">
    <div class="max-w-5xl mx-auto">
        <!-- Action Bar (No Print) -->
        <div class="no-print mb-6 flex justify-between items-center bg-white p-4 rounded-lg shadow-sm">
            <a href="{{ route('campaigns.show', $campaign) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">
                &larr; Back to Campaign
            </a>
            <button onclick="window.print()" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-medium shadow-sm flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Print / Save PDF
            </button>
        </div>

        <!-- Report Container -->
        <div class="bg-white rounded-xl shadow-lg print-shadow-none overflow-hidden">
            <!-- Header -->
            <div class="p-8 border-b border-gray-200 bg-gray-50">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ $campaign->name }}</h1>
                        <p class="text-gray-500">Comprehensive Campaign Performance Report</p>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-3 py-1 text-sm font-semibold rounded-full border 
                            @if($campaign->status === 'completed') bg-green-50 text-green-700 border-green-200
                            @elseif($campaign->status === 'sending') bg-blue-50 text-blue-700 border-blue-200
                            @else bg-gray-50 text-gray-700 border-gray-200 @endif">
                            {{ strtoupper($campaign->status) }}
                        </span>
                    </div>
                </div>

                <div class="mt-8 grid grid-cols-3 gap-6">
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wider font-semibold mb-1">Started At</p>
                        <p class="text-gray-900 font-medium">{{ $campaign->started_at ? $campaign->started_at->format('M d, Y H:i:s') : 'Not started' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wider font-semibold mb-1">Completed At</p>
                        <p class="text-gray-900 font-medium">{{ $campaign->completed_at ? $campaign->completed_at->format('M d, Y H:i:s') : 'In progress' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wider font-semibold mb-1">Duration</p>
                        <p class="text-gray-900 font-medium">{{ $duration ?? '-' }}</p>
                    </div>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="p-8 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-900 mb-6">Key Metrics</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div class="bg-gray-50 p-6 rounded-lg border border-gray-100 text-center">
                        <p class="text-3xl font-bold text-gray-900">{{ $stats['total'] }}</p>
                        <p class="text-sm text-gray-500 font-medium mt-1">Total Recipients</p>
                    </div>
                    <div class="bg-indigo-50 p-6 rounded-lg border border-indigo-100 text-center">
                        <p class="text-3xl font-bold text-indigo-700">{{ $stats['sent'] }}</p>
                        <p class="text-sm text-indigo-500 font-medium mt-1">Successfully Sent</p>
                    </div>
                    <div class="bg-purple-50 p-6 rounded-lg border border-purple-100 text-center">
                        <p class="text-3xl font-bold text-purple-700">{{ $stats['opened'] ?? 0 }}</p>
                        <p class="text-sm text-purple-500 font-medium mt-1">Total Opened</p>
                    </div>
                    <div class="bg-red-50 p-6 rounded-lg border border-red-100 text-center">
                        <p class="text-3xl font-bold text-red-700">{{ $stats['failed'] }}</p>
                        <p class="text-sm text-red-500 font-medium mt-1">Failed Deliveries</p>
                    </div>
                </div>
                
                @if($stats['sent'] > 0)
                <div class="mt-6 flex items-center justify-between bg-gray-50 rounded-lg p-4 border border-gray-100">
                    <div>
                        <p class="text-sm text-gray-500 font-medium">Delivery Rate</p>
                        <p class="text-xl font-bold text-gray-900">{{ number_format(($stats['sent'] / $stats['total']) * 100, 1) }}%</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-500 font-medium">Open Rate (based on sent)</p>
                        <p class="text-xl font-bold text-purple-600">{{ number_format((($stats['opened'] ?? 0) / $stats['sent']) * 100, 1) }}%</p>
                    </div>
                </div>
                @endif
            </div>

            <!-- Content Performance -->
            <div class="p-8 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-900 mb-6">Content Performance</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Subjects -->
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Subject Lines</h3>
                        <div class="space-y-3">
                            @foreach($subjectStats as $subject)
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded border border-gray-100">
                                    <p class="text-sm text-gray-900 flex-1 pr-4">{{ $subject['subject'] }}</p>
                                    <span class="text-sm font-bold text-indigo-600 bg-indigo-50 px-2 py-1 rounded">{{ $subject['usage_count'] }}x</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <!-- Bodies -->
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Body Templates</h3>
                        <div class="space-y-3">
                            @foreach($bodyStats as $index => $body)
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded border border-gray-100">
                                    <p class="text-sm text-gray-900 flex-1 pr-4">Template #{{ $index + 1 }}: <span class="text-gray-500">{{ Str::limit($body['preview'], 40) }}</span></p>
                                    <span class="text-sm font-bold text-indigo-600 bg-indigo-50 px-2 py-1 rounded">{{ $body['usage_count'] }}x</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error Breakdown -->
            @if(count($errorCategories) > 0)
            <div class="p-8">
                <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                    <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    Error Breakdown
                </h2>
                <div class="bg-red-50 rounded-lg p-6 border border-red-100">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($errorCategories as $category => $count)
                            <div class="flex justify-between items-center bg-white p-3 rounded shadow-sm border border-red-50">
                                <span class="text-sm font-medium text-gray-800">{{ $category }}</span>
                                <span class="text-sm font-bold text-red-600 bg-red-100 px-3 py-1 rounded-full">{{ $count }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>
        
        <div class="text-center mt-8 text-sm text-gray-400 no-print">
            Generated by EmailPro Report System
        </div>
    </div>
</body>
</html>
