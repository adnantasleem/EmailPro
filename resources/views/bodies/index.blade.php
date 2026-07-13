<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Body Templates') }}</h2>
            <div class="flex items-center space-x-2">
                <a href="{{ route('body-groups.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    View Groups
                </a>
                <button type="button" onclick="document.getElementById('createGroupModal').classList.remove('hidden')" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 transition" style="background-color: #059669;">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14v6m-3-3h6M6 10h2a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2zm10 0h2a2 2 0 002-2V6a2 2 0 00-2-2h-2a2 2 0 00-2 2v2a2 2 0 002 2zM6 20h2a2 2 0 002-2v-2a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2z"/></svg>
                    New Group
                </button>
                <a href="{{ route('bodies.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 transition" style="background-color: #4338CA;">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Template
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($bodyTemplates as $body)
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div class="p-4 border-b">
                            <h3 class="font-medium text-gray-900">{{ $body->name ?? 'Untitled' }}</h3>
                            <div class="flex items-center gap-2 mt-1">
                                <p class="text-xs text-gray-500">Used {{ $body->usage_count }} times</p>
                                @if($body->bodyGroup)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">
                                        {{ $body->bodyGroup->name }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="p-4 bg-gray-50 h-32 overflow-hidden text-xs text-gray-600">
                            {!! Str::limit(strip_tags($body->html_content), 200) !!}
                        </div>
                        <div class="p-3 flex justify-between items-center border-t">
                            <span class="text-xs text-gray-500">{{ $body->created_at->diffForHumans() }}</span>
                            <div class="flex space-x-2">
                                <a href="{{ route('bodies.edit', $body) }}" class="text-indigo-600 hover:text-indigo-900 text-sm">Edit</a>
                                <form action="{{ route('bodies.destroy', $body) }}" method="POST" class="inline" onsubmit="return confirm('Delete this template?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900 text-sm">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full p-12 text-center text-gray-500 bg-white rounded-lg shadow-sm">
                        <p class="text-lg font-medium">No body templates yet</p>
                        <p class="mt-1 text-sm">Create reusable email body templates to use across campaigns.</p>
                        <a href="{{ route('bodies.create') }}" class="mt-4 inline-flex items-center px-4 py-2 text-white text-sm font-medium rounded-md hover:opacity-90 transition" style="background-color: #4338CA;">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Create Body Template
                        </a>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Create Group Modal -->
    <div id="createGroupModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Create Body Group</h3>
            <form method="POST" action="{{ route('body-groups.store') }}">
                @csrf
                <div class="mb-4">
                    <x-input-label for="group_name" :value="__('Group Name')" />
                    <x-text-input id="group_name" name="name" type="text" class="mt-1 block w-full" required placeholder="e.g., Sales Templates" />
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('createGroupModal').classList.add('hidden')" class="px-4 py-2 text-sm text-gray-700 hover:text-gray-900">Cancel</button>
                    <x-primary-button>Create Group</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
