<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center space-x-4">
            <a href="{{ route('inbox.index') }}" class="text-gray-500 hover:text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $reply->subject ?? '(No Subject)' }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    
                    <!-- Header Info -->
                    <div class="flex justify-between items-start mb-8 pb-6 border-b border-gray-100">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-12 w-12 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 font-bold text-xl">
                                {{ strtoupper(substr($reply->from_email, 0, 1)) }}
                            </div>
                            <div class="ml-4">
                                <div class="text-lg font-medium text-gray-900">
                                    {{ $reply->from_email }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    Reply to Campaign: <a href="{{ route('campaigns.show', $reply->campaign_id) }}" class="text-indigo-600 hover:text-indigo-900">{{ $reply->campaign->name ?? 'Unknown' }}</a>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-gray-500" title="{{ $reply->received_at->format('M d, Y H:i:s') }}">
                                {{ $reply->received_at->format('M d, Y') }} at {{ $reply->received_at->format('h:i A') }}
                            </div>
                            <div class="text-xs text-gray-400 mt-1">
                                via {{ $reply->smtpConfig->username ?? 'Unknown SMTP' }}
                            </div>
                        </div>
                    </div>

                    <!-- Email Body -->
                    <div class="prose max-w-none text-gray-800">
                        @if($reply->body_html)
                            <!-- Use an iframe to sandbox HTML if needed, but for now we just render it safely or raw if trusted -->
                            <div class="email-content bg-gray-50 p-6 rounded-lg border border-gray-100 shadow-inner overflow-x-auto">
                                {!! $reply->body_html !!}
                            </div>
                        @elseif($reply->body_text)
                            <div class="whitespace-pre-wrap font-sans bg-gray-50 p-6 rounded-lg border border-gray-100 shadow-inner">
                                {{ $reply->body_text }}
                            </div>
                        @else
                            <div class="italic text-gray-500">No content available.</div>
                        @endif
                    </div>
                    
                </div>
                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 sm:flex sm:flex-row-reverse">
                    <a href="mailto:{{ $reply->from_email }}?subject=Re: {{ rawurlencode($reply->subject) }}" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Reply via Email Client
                    </a>
                    <a href="{{ route('inbox.index') }}" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Back to Inbox
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
