<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Contact Lists') }}</h2>
            <a href="{{ route('contact-lists.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 transition" style="background-color: #4338CA;">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New List
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="divide-y divide-gray-200">
                    @forelse($contactLists as $list)
                        <div class="p-6 hover:bg-gray-50 flex justify-between items-center">
                            <div>
                                <a href="{{ route('contact-lists.show', $list) }}" class="text-lg font-medium text-gray-900 hover:text-indigo-600">
                                    {{ $list->name }}
                                </a>
                                @if($list->description)
                                    <p class="text-sm text-gray-500 mt-1">{{ Str::limit($list->description, 100) }}</p>
                                @endif
                                <p class="text-sm text-gray-500 mt-1">
                                    📧 {{ $list->contacts_count }} contacts • Created {{ $list->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <div class="flex items-center space-x-3">
                                <a href="{{ route('contact-lists.export', $list) }}" class="text-gray-600 hover:text-gray-900 text-sm">Export</a>
                                <a href="{{ route('contact-lists.show', $list) }}" class="px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 rounded-md">View</a>
                                <form action="{{ route('contact-lists.destroy', $list) }}" method="POST" class="inline" onsubmit="return confirm('Delete this list?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900 text-sm">Delete</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="p-12 text-center text-gray-500">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No contact lists</h3>
                            <p class="mt-1 text-sm text-gray-500">Create a contact list to store your emails.</p>
                            <div class="mt-6">
                                <a href="{{ route('contact-lists.create') }}" class="inline-flex items-center px-4 py-2 text-white text-sm font-medium rounded-md hover:opacity-90 transition" style="background-color: #4338CA;">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    Create Contact List
                                </a>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
