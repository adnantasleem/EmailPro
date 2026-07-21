<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Edit User') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('admin.users.update', $user) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                        <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('name')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('email')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-gray-700">New Password <span class="text-gray-400">(leave blank to keep current)</span></label>
                        <input type="password" name="password" id="password"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('password')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_admin" value="1" {{ old('is_admin', $user->is_admin) ? 'checked' : '' }}
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <span class="ml-2 text-sm text-gray-700">Admin user (can manage other users)</span>
                        </label>
                    </div>

                    <div class="mb-4">
                        <label for="daily_email_limit" class="block text-sm font-medium text-gray-700">Daily Email Limit</label>
                        <input type="number" name="daily_email_limit" id="daily_email_limit" 
                            value="{{ old('daily_email_limit', $user->daily_email_limit) }}" min="0"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="0 = Unlimited">
                        <p class="text-xs text-gray-500 mt-1">Leave empty or set to 0 for unlimited emails.</p>
                        @if($user->daily_email_limit)
                            <p class="text-xs text-blue-600 mt-1">
                                📊 Today: {{ number_format($user->emails_sent_this_day) }} / {{ number_format($user->daily_email_limit) }} emails sent
                                ({{ number_format($user->remaining_daily_email_quota) }} remaining)
                            </p>
                        @endif
                        @error('daily_email_limit')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="monthly_email_limit" class="block text-sm font-medium text-gray-700">Monthly Email Limit</label>
                        <input type="number" name="monthly_email_limit" id="monthly_email_limit" 
                            value="{{ old('monthly_email_limit', $user->monthly_email_limit) }}" min="0"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="0 = Unlimited">
                        <p class="text-xs text-gray-500 mt-1">Leave empty or set to 0 for unlimited emails. Limit resets on the 1st of each month.</p>
                        @if($user->monthly_email_limit)
                            <p class="text-xs text-blue-600 mt-1">
                                📊 This month: {{ number_format($user->emails_sent_this_month) }} / {{ number_format($user->monthly_email_limit) }} emails sent
                                ({{ number_format($user->remaining_email_quota) }} remaining)
                            </p>
                        @endif
                        @error('monthly_email_limit')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div class="mb-4">
                        <label for="yearly_email_limit" class="block text-sm font-medium text-gray-700">Yearly Email Limit</label>
                        <input type="number" name="yearly_email_limit" id="yearly_email_limit" 
                            value="{{ old('yearly_email_limit', $user->yearly_email_limit) }}" min="0"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="0 = Unlimited">
                        <p class="text-xs text-gray-500 mt-1">Leave empty or set to 0 for unlimited emails.</p>
                        @if($user->yearly_email_limit)
                            <p class="text-xs text-blue-600 mt-1">
                                📊 This year: {{ number_format($user->emails_sent_this_year) }} / {{ number_format($user->yearly_email_limit) }} emails sent
                                ({{ number_format($user->remaining_yearly_email_quota) }} remaining)
                            </p>
                        @endif
                        @error('yearly_email_limit')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div class="mb-6">
                        <label for="expires_at" class="block text-sm font-medium text-gray-700">Account Expiration Date</label>
                        <input type="datetime-local" name="expires_at" id="expires_at" 
                            value="{{ old('expires_at', $user->expires_at ? $user->expires_at->format('Y-m-d\TH:i') : '') }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="text-xs text-gray-500 mt-1">Optional. The user's account will be blocked and they won't be able to log in or send campaigns after this date.</p>
                        @if($user->expires_at)
                            <p class="text-xs {{ $user->isAccountExpired() ? 'text-red-600 font-bold' : 'text-blue-600' }} mt-1">
                                🕒 Status: {{ $user->isAccountExpired() ? 'Expired' : 'Active (expires in ' . $user->expires_at->diffForHumans() . ')' }}
                            </p>
                        @endif
                        @error('expires_at')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6 pt-4 border-t">
                        <label class="block text-sm font-medium text-gray-700 mb-2">SMTP Management</label>
                        <p class="text-xs text-gray-500 mb-3">Who manages this user's SMTP accounts?</p>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="radio" name="manages_own_smtp" value="1" {{ old('manages_own_smtp', $user->manages_own_smtp) ? 'checked' : '' }} class="text-indigo-600 focus:ring-indigo-500 h-4 w-4 border-gray-300">
                                <span class="ml-2 text-sm text-gray-700">User manages their own SMTP accounts</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="manages_own_smtp" value="0" {{ !old('manages_own_smtp', $user->manages_own_smtp) ? 'checked' : '' }} class="text-indigo-600 focus:ring-indigo-500 h-4 w-4 border-gray-300">
                                <span class="ml-2 text-sm text-gray-700">Admin provides SMTP account (Hide SMTP menu from user)</span>
                            </label>
                        </div>
                        @error('manages_own_smtp')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('admin.users.index') }}" class="text-gray-600 hover:text-gray-900 text-sm">Cancel</a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 transition" style="background-color: #4338CA;">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
