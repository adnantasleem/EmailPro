<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Unsubscribed Emails') }}</h2>
                <p class="text-sm text-gray-500">{{ $totalCount }} emails will be excluded from all campaigns</p>
            </div>
            <a href="{{ route('unsubscribes.export') }}" class="px-3 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 text-sm">Export CSV</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Unsubscribes Table -->
                <div class="lg:col-span-2 bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse($unsubscribes as $unsub)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ $unsub->email }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $unsub->reason ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $unsub->unsubscribed_at->format('M d, Y H:i') }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <form action="{{ route('unsubscribes.destroy', $unsub) }}" method="POST" class="inline" onsubmit="return confirm('Remove from unsubscribe list?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-green-600 hover:text-green-900 text-sm">Re-enable</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-gray-500">No unsubscribed emails yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t border-gray-200">{{ $unsubscribes->links() }}</div>
                </div>

                <!-- Add Unsubscribes -->
                <div class="space-y-6">
                    <!-- Single Email -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Add Single Email</h3>
                        <form method="POST" action="{{ route('unsubscribes.store') }}">
                            @csrf
                            <div class="mb-4">
                                <x-input-label for="email" :value="__('Email')" />
                                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" required placeholder="email@example.com" />
                            </div>
                            <div class="mb-4">
                                <x-input-label for="reason" :value="__('Reason (Optional)')" />
                                <x-text-input id="reason" name="reason" type="text" class="mt-1 block w-full" placeholder="e.g., Requested removal" />
                            </div>
                            <x-primary-button class="w-full justify-center">Add to Unsubscribe List</x-primary-button>
                        </form>
                    </div>

                    <!-- Bulk Import -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Bulk Import</h3>
                        <form method="POST" action="{{ route('unsubscribes.import') }}" enctype="multipart/form-data">
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
                                <textarea id="emails" name="emails" rows="5" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" placeholder="One per line..."></textarea>
                            </div>
                            <x-primary-button class="w-full justify-center">Import</x-primary-button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
