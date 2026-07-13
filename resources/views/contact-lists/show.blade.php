<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $contactList->name }}</h2>
                <p class="text-sm text-gray-500">{{ $contactList->contacts_count }} contacts</p>
            </div>
            <div class="flex space-x-2">
                {{-- Export Dropdown --}}
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="px-3 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 text-sm inline-flex items-center">
                        📥 Export
                        <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-50 border">
                        <a href="{{ route('contact-lists.export', ['contact_list' => $contactList, 'status' => 'all']) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            📋 Export All ({{ $contactList->contacts_count }})
                        </a>
                        <a href="{{ route('contact-lists.export', ['contact_list' => $contactList, 'status' => 'valid']) }}" class="block px-4 py-2 text-sm text-green-600 hover:bg-gray-100">
                            ✅ Export Valid Only
                        </a>
                        <a href="{{ route('contact-lists.export', ['contact_list' => $contactList, 'status' => 'invalid']) }}" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                            ❌ Export Invalid Only
                        </a>
                        <a href="{{ route('contact-lists.export', ['contact_list' => $contactList, 'status' => 'pending']) }}" class="block px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">
                            ⏳ Export Pending Only
                        </a>
                    </div>
                </div>
                <a href="{{ route('contact-lists.edit', $contactList) }}" class="px-3 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 text-sm">Edit</a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{{ session('error') }}</div>
            @endif

            {{-- Import Report - Shows when there are skipped emails --}}
            @if(session('import_skipped_emails') && count(session('import_skipped_emails')) > 0)
            <div class="mb-6 bg-amber-50 border border-amber-300 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-amber-800">📋 Import Report</h3>
                        <p class="text-sm text-amber-700 mt-1">
                            <span class="font-bold">{{ count(session('import_skipped_emails')) }}</span> contacts were skipped during import.
                            Download the report to see which emails and why.
                        </p>
                        {{-- Status breakdown --}}
                        @php
                            $skipped = collect(session('import_skipped_emails'));
                            $statusCounts = $skipped->groupBy('status')->map->count();
                        @endphp
                        <div class="flex flex-wrap gap-2 mt-2">
                            @foreach($statusCounts as $status => $count)
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium 
                                    @if($status === 'Duplicate in this list') bg-blue-100 text-blue-800
                                    @elseif($status === 'Already in other list') bg-purple-100 text-purple-800
                                    @elseif($status === 'Unsubscribed') bg-yellow-100 text-yellow-800
                                    @elseif($status === 'Blocklisted') bg-red-100 text-red-800
                                    @elseif($status === 'Invalid Syntax') bg-gray-100 text-gray-800
                                    @else bg-gray-100 text-gray-800
                                    @endif
                                ">
                                    {{ $status }}: {{ $count }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                    <a href="{{ route('contact-lists.download-skipped') }}" 
                       class="inline-flex items-center px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700 text-sm font-medium shadow-sm">
                        📥 Download Skipped Emails
                    </a>
                </div>
            </div>
            @endif

            <!-- Validation Stats Summary - Horizontal at Top -->
            @php
                $validCount = $contactList->contacts()->valid()->count();
                $invalidCount = $contactList->contacts()->invalid()->count();
                $pendingCount = $contactList->contacts()->pendingValidation()->count();
                $validatingCount = $contactList->contacts()->status('validating')->count();
            @endphp
            @if($validCount > 0 || $invalidCount > 0 || $pendingCount > 0 || $validatingCount > 0)
            <div class="flex flex-wrap gap-3 mb-6 items-center">
                <div class="bg-green-50 rounded-lg px-6 py-3 text-center">
                    <div class="text-xl font-bold text-green-600">{{ $validCount }}</div>
                    <div class="text-xs text-green-700">Valid</div>
                    @if($validCount > 0)
                    <a href="{{ route('contact-lists.export', ['contact_list' => $contactList, 'status' => 'valid']) }}" class="text-xs text-green-600 hover:underline">📥 Download</a>
                    @endif
                </div>
                <div class="bg-red-50 rounded-lg px-6 py-3 text-center">
                    <div class="text-xl font-bold text-red-600">{{ $invalidCount }}</div>
                    <div class="text-xs text-red-700">Invalid</div>
                    @if($invalidCount > 0)
                    <a href="{{ route('contact-lists.export', ['contact_list' => $contactList, 'status' => 'invalid']) }}" class="text-xs text-red-600 hover:underline">📥 Download</a>
                    @endif
                </div>
                <div class="bg-yellow-50 rounded-lg px-6 py-3 text-center">
                    <div class="text-xl font-bold text-yellow-600">{{ $validatingCount }}</div>
                    <div class="text-xs text-yellow-700">Validating</div>
                </div>
                <div class="bg-gray-100 rounded-lg px-6 py-3 text-center">
                    <div class="text-xl font-bold text-gray-600">{{ $pendingCount }}</div>
                    <div class="text-xs text-gray-700">Pending</div>
                    @if($pendingCount > 0)
                    <a href="{{ route('contact-lists.export', ['contact_list' => $contactList, 'status' => 'pending']) }}" class="text-xs text-gray-600 hover:underline">📥 Download</a>
                    @endif
                </div>
                @if($invalidCount > 0)
                <form action="{{ route('contact-lists.delete-invalid', $contactList) }}" method="POST" class="inline" onsubmit="return confirm('Delete all {{ $invalidCount }} invalid contacts? This cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm font-medium">
                        🗑️ Delete {{ $invalidCount }} Invalid
                    </button>
                </form>
                @endif
            </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white rounded-lg shadow-sm overflow-hidden">
                    <!-- Enhanced Search and Filter Controls -->
                    <div class="p-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
                        <form id="searchForm" method="GET" action="{{ route('contact-lists.show', $contactList) }}">
                            <div class="flex flex-col sm:flex-row gap-3">
                                <!-- Search Input Container -->
                                <div class="relative flex-1" style="min-height: 48px;">
                                    <!-- Search Icon -->
                                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-indigo-500" style="top: 50%;left:10px">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                    </span>
                                    
                                    <!-- Input -->
                                    <input type="text" 
                                        name="search" 
                                        id="searchInput"
                                        value="{{ request('search') }}" 
                                        placeholder="Search contacts by email or name..." 
                                        autocomplete="off"
                                        style="padding-left: 42px; padding-right: 42px;"
                                        class="w-full h-12 border border-gray-200 rounded-xl shadow-sm bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm placeholder-gray-400">
                                    
                                    <!-- Spinner (hidden by default) -->
                                    <span id="searchSpinner" class="absolute right-3 top-1/2 transform -translate-y-1/2 hidden" style="top: 30%;right:20px">
                                        <svg class="animate-spin w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                    </span>
                                    
                                    <!-- Clear X Button -->
                                    @if(request('search'))
                                    <a href="{{ route('contact-lists.show', ['contact_list' => $contactList, 'status' => request('status')]) }}" 
                                       id="clearSearch"
                                       class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-red-500" style="top: 30%;right:20px">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </a>
                                    @endif
                                </div>
                                
                                <!-- Status Filter Pills -->
                                <div class="flex items-center gap-2 flex-wrap">
                                    <a href="{{ route('contact-lists.show', ['contact_list' => $contactList, 'search' => request('search')]) }}" 
                                       class="px-3 py-2 rounded-lg text-sm font-medium transition-all {{ !request('status') || request('status') == 'all' ? 'bg-indigo-600 text-white shadow-md' : 'bg-white text-gray-600 hover:bg-gray-100' }}">
                                        All
                                    </a>
                                    <a href="{{ route('contact-lists.show', ['contact_list' => $contactList, 'status' => 'valid', 'search' => request('search')]) }}" 
                                       class="px-3 py-2 rounded-lg text-sm font-medium transition-all {{ request('status') == 'valid' ? 'bg-green-600 text-white shadow-md' : 'bg-white text-gray-600 hover:bg-gray-100' }}">
                                        ✓ Valid
                                    </a>
                                    <a href="{{ route('contact-lists.show', ['contact_list' => $contactList, 'status' => 'invalid', 'search' => request('search')]) }}" 
                                       class="px-3 py-2 rounded-lg text-sm font-medium transition-all {{ request('status') == 'invalid' ? 'bg-red-600 text-white shadow-md' : 'bg-white text-gray-600 hover:bg-gray-100' }}">
                                        ✗ Invalid
                                    </a>
                                    <a href="{{ route('contact-lists.show', ['contact_list' => $contactList, 'status' => 'pending', 'search' => request('search')]) }}" 
                                       class="px-3 py-2 rounded-lg text-sm font-medium transition-all {{ request('status') == 'pending' ? 'bg-gray-600 text-white shadow-md' : 'bg-white text-gray-600 hover:bg-gray-100' }}">
                                        ⏳ Pending
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Result Count -->
                            <div class="mt-3 flex items-center justify-between text-sm">
                                <span class="text-gray-600">
                                    @if(request('search') || (request('status') && request('status') != 'all'))
                                        <span class="font-medium text-indigo-600">{{ $contacts->total() }}</span> results found
                                    @else
                                        <span class="font-medium">{{ $contacts->total() }}</span> total contacts
                                    @endif
                                </span>
                                @if(request('search') || request('status'))
                                <a href="{{ route('contact-lists.show', $contactList) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">
                                    Clear all filters
                                </a>
                                @endif
                            </div>
                        </form>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200" id="contactsTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Added</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200" id="contactsBody">
                                @forelse($contacts as $contact)
                                    <tr class="contact-row" 
                                        data-email="{{ strtolower($contact->email) }}" 
                                        data-name="{{ strtolower($contact->name ?? '') }}"
                                        data-status="{{ $contact->validation_status }}">
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ $contact->email }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $contact->name ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            @switch($contact->validation_status)
                                                @case('valid')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800" title="Email validated successfully">
                                                        ✓ Valid
                                                    </span>
                                                    @break
                                                @case('invalid')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800" title="{{ $contact->validation_error }}">
                                                        ✗ Invalid
                                                    </span>
                                                    @break
                                                @case('validating')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                        ⏳ Validating
                                                    </span>
                                                    @break
                                                @default
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                        ⏳ Pending
                                                    </span>
                                            @endswitch
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $contact->created_at->format('M d, Y') }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <form action="{{ route('contact-lists.delete-contact', [$contactList, $contact]) }}" method="POST" class="inline" onsubmit="return confirm('Delete this contact?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900 text-sm">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">No contacts yet. Add some using the form.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t border-gray-200">{{ $contacts->links() }}</div>
                </div>

                <!-- Add Contacts Form -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Add Contacts</h3>
                    <form method="POST" action="{{ route('contact-lists.add-contacts', $contactList) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-4">
                            <x-input-label for="file" :value="__('Upload CSV/TXT')" />
                            <input type="file" name="file" id="file" accept=".csv,.txt" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700">
                        </div>
                        <div class="relative my-4">
                            <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                            <div class="relative flex justify-center text-sm"><span class="px-2 bg-white text-gray-500">Or</span></div>
                        </div>
                        <div class="mb-4">
                            <x-input-label for="emails" :value="__('Paste Emails')" />
                            <textarea id="emails" name="emails" rows="6" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" placeholder="One per line..."></textarea>
                        </div>
                        <x-primary-button class="w-full justify-center">Add Contacts</x-primary-button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Debounced search as you type --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const searchForm = document.getElementById('searchForm');
            const searchSpinner = document.getElementById('searchSpinner');
            let debounceTimer;

            searchInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                
                // Show spinner
                searchSpinner.classList.remove('hidden');
                
                debounceTimer = setTimeout(() => {
                    // Submit the form after 500ms of no typing
                    searchForm.submit();
                }, 500);
            });

            // Also submit on Enter key immediately
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    clearTimeout(debounceTimer);
                    searchSpinner.classList.remove('hidden');
                    searchForm.submit();
                }
            });
        });
    </script>

    {{-- Auto-refresh when there are pending or validating contacts --}}
    @if($pendingCount > 0 || $validatingCount > 0)
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
