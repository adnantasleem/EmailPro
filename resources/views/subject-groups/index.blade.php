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
        </div>
    </div>
</x-app-layout>
