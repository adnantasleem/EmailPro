<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Subject Groups') }}</h2>
            <a href="{{ route('subjects.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">← Back to Subjects</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif

            <!-- Search Form -->
            <div class="mb-6 bg-white p-4 rounded-lg shadow-sm">
                <form action="{{ route('subject-groups.index') }}" method="GET" class="flex items-center space-x-4">
                    <div class="flex-grow">
                        <label for="search" class="sr-only">Search</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                            <input type="text" name="search" id="search" value="{{ request('search') }}" class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 sm:text-sm border-gray-300 rounded-md" placeholder="Search subject groups...">
                        </div>
                    </div>
                    <div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Search
                        </button>
                    </div>
                    @if(request('search'))
                    <div>
                        <a href="{{ route('subject-groups.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Clear
                        </a>
                    </div>
                    @endif
                </form>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="divide-y divide-gray-200">
                    @forelse($groups as $group)
                        <div class="p-4 hover:bg-gray-50 flex justify-between items-center">
                            <div class="flex-1">
                                <a href="{{ route('subject-groups.show', $group) }}" class="text-gray-900 font-medium hover:text-indigo-600">
                                    {{ $group->name }}
                                </a>
                                <p class="text-xs text-gray-500 mt-1">{{ $group->subject_lines_count }} subject(s) • Created {{ $group->created_at->diffForHumans() }}</p>
                            </div>
                            <div class="flex items-center space-x-3 ml-4">
                                <a href="{{ route('subject-groups.show', $group) }}" class="text-indigo-600 hover:text-indigo-900 text-sm">View</a>
                                <form action="{{ route('subject-groups.destroy', $group) }}" method="POST" class="inline" onsubmit="return confirm('Delete this group? Subject lines will be ungrouped, not deleted.')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900 text-sm">Delete</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="p-12 text-center text-gray-500">
                            <p class="text-lg font-medium">No subject groups yet</p>
                            <p class="mt-1 text-sm">Create groups to organize your subject lines.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            @if($groups->hasPages())
                <div class="mt-6">
                    {{ $groups->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
