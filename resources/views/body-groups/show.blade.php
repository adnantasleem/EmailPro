<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Group: {{ $bodyGroup->name }}
            </h2>
            <a href="{{ route('body-groups.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">← Back to Groups</a>
        </div>
    </x-slot>

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container--default .select2-selection--multiple {
            border-color: #d1d5db;
            border-radius: 0.375rem;
            min-height: 42px;
            padding: 2px 8px;
        }
        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: #6366f1;
            box-shadow: 0 0 0 1px #6366f1;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #6366f1;
            border: none;
            color: white;
            border-radius: 4px;
            padding: 2px 8px;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: white;
            margin-right: 5px;
        }
    </style>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif

            <!-- Edit Group Name -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mb-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Group Settings</h3>
                <form method="POST" action="{{ route('body-groups.update', $bodyGroup) }}" class="flex gap-3 items-end">
                    @csrf @method('PUT')
                    <div class="flex-1">
                        <x-input-label for="name" :value="__('Group Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="$bodyGroup->name" required />
                    </div>
                    <x-primary-button>Update Name</x-primary-button>
                </form>
            </div>

            <!-- Add Existing Bodies to Group -->
            @if($ungroupedBodies->count() > 0)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mb-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add Templates to Group</h3>
                <form method="POST" action="{{ route('body-groups.add-bodies', $bodyGroup) }}">
                    @csrf
                    <div class="mb-4">
                        <select name="body_ids[]" id="ungrouped_bodies" multiple class="mt-1 block w-full">
                            @foreach($ungroupedBodies as $body)
                                <option value="{{ $body->id }}">{{ $body->name ?? 'Template #' . $body->id }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-primary-button>Add Selected to Group</x-primary-button>
                </form>
            </div>
            @endif

            <!-- Templates in this Group -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 border-b bg-gray-50">
                    <h3 class="text-lg font-medium text-gray-900">
                        Templates in this Group ({{ $bodyGroup->bodyTemplates->count() }})
                    </h3>
                </div>
                <div class="divide-y divide-gray-200">
                    @forelse($bodyGroup->bodyTemplates as $body)
                        <div class="p-4 hover:bg-gray-50">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h4 class="font-medium text-gray-900">{{ $body->name ?? 'Untitled' }}</h4>
                                    <p class="text-xs text-gray-500 mt-1">Used {{ $body->usage_count }} times • Created {{ $body->created_at->diffForHumans() }}</p>
                                    <div class="mt-2 p-3 bg-gray-50 rounded text-xs text-gray-600 max-h-24 overflow-hidden">
                                        {!! Str::limit(strip_tags($body->html_content), 200) !!}
                                    </div>
                                </div>
                                <div class="flex items-center space-x-3 ml-4">
                                    <a href="{{ route('bodies.edit', $body) }}" class="text-indigo-600 hover:text-indigo-900 text-sm">Edit</a>
                                    <form action="{{ route('body-groups.remove-body', [$bodyGroup, $body]) }}" method="POST" class="inline" onsubmit="return confirm('Remove from group? The template will still exist.')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-yellow-600 hover:text-yellow-900 text-sm">Remove</button>
                                    </form>
                                    <form action="{{ route('body-groups.delete-body', [$bodyGroup, $body]) }}" method="POST" class="inline" onsubmit="return confirm('Delete this template permanently?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900 text-sm">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-gray-500">
                            <p>No templates in this group yet.</p>
                            <p class="text-sm mt-1">Use the form above to add existing templates, or create new ones from the <a href="{{ route('bodies.index') }}" class="text-indigo-600 hover:underline">Bodies page</a>.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#ungrouped_bodies').select2({
                placeholder: 'Search and select templates to add...',
                allowClear: true,
                width: '100%'
            });
        });
    </script>
</x-app-layout>
