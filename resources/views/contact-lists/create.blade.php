<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Create Contact List') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('contact-lists.store') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-4">
                        <x-input-label for="name" :value="__('List Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required placeholder="e.g., Newsletter Subscribers" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="mb-4">
                        <x-input-label for="description" :value="__('Description (Optional)')" />
                        <textarea id="description" name="description" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" placeholder="What is this list for?">{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Import Contacts</h3>
                        
                        <!-- File Upload -->
                        <div class="mb-4">
                            <x-input-label for="file" :value="__('Upload CSV/TXT File')" />
                            <input type="file" name="file" id="file" accept=".csv,.txt" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            <x-input-error :messages="$errors->get('file')" class="mt-2" />
                        </div>

                        <div class="relative my-4">
                            <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                            <div class="relative flex justify-center text-sm"><span class="px-2 bg-gray-50 text-gray-500">Or paste emails</span></div>
                        </div>

                        <!-- Text Input -->
                        <div>
                            <x-input-label for="emails" :value="__('Paste Emails')" />
                            <textarea id="emails" name="emails" rows="6" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm font-mono text-sm" placeholder="One email per line or comma-separated&#10;john@example.com&#10;Jane Doe <jane@example.com>">{{ old('emails') }}</textarea>
                            <x-input-error :messages="$errors->get('emails')" class="mt-2" />
                        </div>

                        <div class="mt-3 p-3 bg-blue-50 rounded text-sm text-blue-800">
                            <strong>Supported Formats:</strong><br>
                            • Simple: <code>john@example.com</code> or <code>John Doe &lt;john@example.com&gt;</code><br>
                            • CSV with custom fields (first row = headers):<br>
                            <code class="block mt-1 p-2 bg-blue-100 rounded text-xs">
email,name,company,city<br>
john@example.com,John Doe,Acme Inc,New York<br>
jane@example.com,Jane Smith,Tech Co,Boston
                            </code>
                            <p class="mt-2">Custom columns become variables: <code>@{{company}}</code>, <code>@{{city}}</code></p>
                        </div>
                        
                        <div class="mt-2 p-3 bg-green-50 rounded text-sm text-green-800">
                            ✓ Duplicate emails will be automatically skipped<br>
                            ✓ Unsubscribed emails will be automatically skipped
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-4">
                        <a href="{{ route('contact-lists.index') }}" class="text-gray-600 hover:text-gray-900">Cancel</a>
                        <x-primary-button>{{ __('Create List') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
