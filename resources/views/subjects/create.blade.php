<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Create Subject Line') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('subjects.store') }}">
                    @csrf

                    <div class="mb-6">
                        <x-input-label for="subject" :value="__('Subject Line')" />
                        <x-text-input id="subject" name="subject" type="text" class="mt-1 block w-full" :value="old('subject')" required placeholder="e.g., Check out our latest offers!" />
                        <x-input-error :messages="$errors->get('subject')" class="mt-2" />
                        <p class="mt-2 text-sm text-gray-500">You can use variables: @{{name}}, @{{email}}</p>
                    </div>

                    <div class="mb-6">
                        <x-input-label for="subject_group_id" :value="__('Group (Optional)')" />
                        <select name="subject_group_id" id="subject_group_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">— No Group —</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}" {{ old('subject_group_id') == $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center justify-end gap-4">
                        <a href="{{ route('subjects.index') }}" class="text-gray-600 hover:text-gray-900">Cancel</a>
                        <x-primary-button>{{ __('Create Subject') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
