<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Review Import') }}: {{ $contactList->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 border-b border-gray-200">
                    <h3 class="text-lg font-medium mb-4">Import Summary</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <div class="text-sm text-gray-500 uppercase tracking-wide font-semibold mb-1">Total Processed</div>
                            <div class="text-3xl font-bold text-gray-900">
                                {{ count($importData['valid_emails'] ?? []) + count($importData['skipped_emails'] ?? []) }}
                            </div>
                        </div>
                        
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="text-sm text-green-600 uppercase tracking-wide font-semibold mb-1">Valid (Ready to Save)</div>
                            <div class="text-3xl font-bold text-green-700">
                                {{ count($importData['valid_emails'] ?? []) }}
                            </div>
                        </div>
                        
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="text-sm text-red-600 uppercase tracking-wide font-semibold mb-1">Invalid (Skipped)</div>
                            <div class="text-3xl font-bold text-red-700">
                                {{ count($importData['skipped_emails'] ?? []) }}
                            </div>
                            <div class="text-xs text-red-500 mt-1">
                                Duplicates, Unsubscribed, Invalid Syntax, etc.
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4 mt-8 justify-between items-center bg-gray-50 p-4 border border-gray-200 rounded-lg">
                        <div>
                            <a href="{{ route('contact-lists.download-report', [$contactList->id, $importId]) }}" class="inline-flex justify-center items-center px-4 py-2 border border-blue-600 text-blue-600 rounded-md font-semibold text-sm hover:bg-blue-50 transition">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                Download Full Report
                            </a>
                        </div>
                        
                        <div class="flex gap-4">
                            <a href="{{ route('contact-lists.show', $contactList->id) }}" class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-gray-700 hover:bg-white transition bg-gray-100">
                                Cancel
                            </a>

                            <form action="{{ route('contact-lists.commit-import', [$contactList->id, $importId]) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-white hover:bg-indigo-700 transition">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    Proceed with {{ count($importData['valid_emails'] ?? []) }} Valid Contacts
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview Valid Data -->
            @if(!empty($importData['valid_emails']))
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Preview of Valid Contacts</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-100">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach(array_slice($importData['valid_emails'], 0, 5) as $valid)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $valid['email'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $valid['name'] ?? '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-medium">OK</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if(count($importData['valid_emails']) > 5)
                        <div class="mt-4 text-sm text-gray-500 text-center">
                            Showing 5 of {{ count($importData['valid_emails']) }} valid contacts. Download the full report to see all.
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            <!-- Preview Skipped Data -->
            @if(!empty($importData['skipped_emails']))
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Preview of Skipped Contacts</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason Skipped</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach(array_slice($importData['skipped_emails'], 0, 10) as $skipped)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $skipped['email'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-medium">{{ $skipped['status'] }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if(count($importData['skipped_emails']) > 10)
                        <div class="mt-4 text-sm text-gray-500 text-center">
                            Showing 10 of {{ count($importData['skipped_emails']) }} skipped contacts. Download the full report to see all.
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
