<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Blocked Domains') }}
            </h2>
            <span class="text-sm text-gray-500">{{ $domains->total() }} domains</span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Domains List -->
                <div class="lg:col-span-2 bg-white rounded-lg shadow-sm overflow-hidden">
                    <!-- Search -->
                    <div class="p-4 border-b border-gray-200">
                        <form method="GET" class="flex gap-2">
                            <input type="text" name="search" value="{{ request('search') }}" 
                                   placeholder="Search domains..." 
                                   class="flex-1 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                            <x-primary-button>Search</x-primary-button>
                            @if(request('search'))
                                <a href="{{ route('disposable-domains.index') }}" class="px-3 py-2 text-gray-600 hover:text-gray-900">Clear</a>
                            @endif
                        </form>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Domain</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Added</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse($domains as $domain)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ $domain->domain }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $domain->created_at->format('M d, Y') }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <form action="{{ route('disposable-domains.destroy', $domain) }}" method="POST" class="inline" onsubmit="return confirm('Remove this domain?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900 text-sm">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-8 text-center text-gray-500">
                                            No blocked domains. Add some using the form.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="p-4 border-t border-gray-200">
                        {{ $domains->links() }}
                    </div>

                    <!-- Delete All Button -->
                    @if($domains->total() > 0)
                    <div class="p-4 border-t border-gray-200 bg-gray-50">
                        <form action="{{ route('disposable-domains.destroy-all') }}" method="POST" onsubmit="return confirm('Are you sure you want to remove ALL blocked domains? This cannot be undone.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-900 text-sm">
                                Remove All {{ $domains->total() }} Domains
                            </button>
                        </form>
                    </div>
                    @endif
                </div>

                <!-- Add Domain Form -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Add Blocked Domain</h3>
                    
                    <form method="POST" action="{{ route('disposable-domains.store') }}" class="mb-6">
                        @csrf
                        <div class="mb-4">
                            <x-input-label for="domain" :value="__('Domain')" />
                            <x-text-input id="domain" name="domain" type="text" class="mt-1 block w-full" placeholder="example.com" required />
                            <x-input-error :messages="$errors->get('domain')" class="mt-2" />
                        </div>
                        <x-primary-button class="w-full justify-center">Add Domain</x-primary-button>
                    </form>

                    <div class="relative my-4">
                        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                        <div class="relative flex justify-center text-sm"><span class="px-2 bg-white text-gray-500">Or</span></div>
                    </div>

                    <!-- Bulk Import -->
                    <h4 class="text-md font-medium text-gray-900 mb-2">Import Multiple</h4>
                    <form method="POST" action="{{ route('disposable-domains.import') }}">
                        @csrf
                        <div class="mb-4">
                            <x-input-label for="domains" :value="__('Domains (one per line)')" />
                            <textarea id="domains" name="domains" rows="6" 
                                      class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" 
                                      placeholder="tempmail.com&#10;throwaway.com&#10;10minutemail.com"></textarea>
                            <x-input-error :messages="$errors->get('domains')" class="mt-2" />
                        </div>
                        <x-primary-button class="w-full justify-center">Import Domains</x-primary-button>
                    </form>

                    <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">What are blocked domains?</h4>
                        <p class="text-xs text-gray-600">
                            Blocked domains are disposable email providers. Contacts with emails from these domains 
                            will be automatically rejected during import to protect your sender reputation.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
