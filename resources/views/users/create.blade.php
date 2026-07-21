<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Add New User') }}</h2>
    </x-slot>

    <div class="py-12" x-data="{ 
        managesSmtp: '{{ old('manages_own_smtp', '1') }}',
        smtps: [
            { name: '', host: '', port: '587', encryption: 'tls', from_name: '', username: '', password: '', pacing_strategy: 'per_hour', daily_limit: '', min_emails_per_hour: '', max_emails_per_hour: '', min_emails_per_day: '', max_emails_per_day: '' }
        ],
        addSmtp() {
            this.smtps.push({ name: '', host: '', port: '587', encryption: 'tls', from_name: '', username: '', password: '', pacing_strategy: 'per_hour', daily_limit: '', min_emails_per_hour: '', max_emails_per_hour: '', min_emails_per_day: '', max_emails_per_day: '' });
        },
        removeSmtp(index) {
            if(this.smtps.length > 1) {
                this.smtps.splice(index, 1);
            }
        }
    }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Left Column: User Details -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2">User Details</h3>

                        <div class="mb-4">
                            <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('name')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" name="email" id="email" value="{{ old('email') }}" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('email')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                            <input type="password" name="password" id="password" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('password')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_admin" value="1" {{ old('is_admin') ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">Admin user (can manage other users)</span>
                            </label>
                        </div>

                        <div class="mb-4">
                            <label for="daily_email_limit" class="block text-sm font-medium text-gray-700">Daily Email Limit</label>
                            <input type="number" name="daily_email_limit" id="daily_email_limit" value="{{ old('daily_email_limit') }}" min="0"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="0 = Unlimited">
                            <p class="text-xs text-gray-500 mt-1">Leave empty or set to 0 for unlimited emails.</p>
                            @error('daily_email_limit')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="monthly_email_limit" class="block text-sm font-medium text-gray-700">Monthly Email Limit</label>
                            <input type="number" name="monthly_email_limit" id="monthly_email_limit" value="{{ old('monthly_email_limit') }}" min="0"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="0 = Unlimited">
                            <p class="text-xs text-gray-500 mt-1">Leave empty or set to 0 for unlimited emails. Limit resets on the 1st of each month.</p>
                            @error('monthly_email_limit')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div class="mb-4">
                            <label for="yearly_email_limit" class="block text-sm font-medium text-gray-700">Yearly Email Limit</label>
                            <input type="number" name="yearly_email_limit" id="yearly_email_limit" value="{{ old('yearly_email_limit') }}" min="0"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="0 = Unlimited">
                            <p class="text-xs text-gray-500 mt-1">Leave empty or set to 0 for unlimited emails.</p>
                            @error('yearly_email_limit')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div class="mb-4">
                            <label for="expires_at" class="block text-sm font-medium text-gray-700">Account Expiration Date</label>
                            <input type="datetime-local" name="expires_at" id="expires_at" value="{{ old('expires_at') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="text-xs text-gray-500 mt-1">Optional. The user's account will be blocked and they won't be able to log in or send campaigns after this date.</p>
                            @error('expires_at')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div> <!-- End Left Column -->

                    <!-- Right Column: SMTP Management -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 border-b pb-2">SMTP Management</h3>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Who manages this user's SMTP accounts?</label>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input type="radio" name="manages_own_smtp" value="1" x-model="managesSmtp" class="text-indigo-600 focus:ring-indigo-500 h-4 w-4 border-gray-300">
                                    <span class="ml-2 text-sm text-gray-700">User manages their own SMTP accounts</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="manages_own_smtp" value="0" x-model="managesSmtp" class="text-indigo-600 focus:ring-indigo-500 h-4 w-4 border-gray-300">
                                    <span class="ml-2 text-sm text-gray-700">Admin provides SMTP account (Hide SMTP menu from user)</span>
                                </label>
                            </div>
                        </div>

                        <!-- Dynamic SMTP Fields -->
                        <div x-show="managesSmtp == '0'" style="display: none;" class="space-y-6 border-t pt-4">
                            
                            <template x-for="(smtp, index) in smtps" :key="index">
                                <div class="p-4 border rounded-lg bg-gray-50 relative">
                                    <button type="button" @click="removeSmtp(index)" x-show="smtps.length > 1" class="absolute top-2 right-2 text-red-500 hover:text-red-700">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                    
                                    <p class="text-sm font-medium text-indigo-600 mb-4">SMTP Account <span x-text="index + 1"></span></p>
                                    
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">Display Name</label>
                                            <input type="text" x-bind:name="'smtps[' + index + '][name]'" x-model="smtp.name" placeholder="e.g. Sales Email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700">Host</label>
                                                <input type="text" x-bind:name="'smtps[' + index + '][host]'" x-model="smtp.host" placeholder="smtp.gmail.com" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700">Port</label>
                                                <input type="number" x-bind:name="'smtps[' + index + '][port]'" x-model="smtp.port" placeholder="587" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700">Encryption</label>
                                                <select x-bind:name="'smtps[' + index + '][encryption]'" x-model="smtp.encryption" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                    <option value="tls">TLS</option>
                                                    <option value="ssl">SSL</option>
                                                    <option value="none">None</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700">From Name</label>
                                                <input type="text" x-bind:name="'smtps[' + index + '][from_name]'" x-model="smtp.from_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">From Email / Username</label>
                                            <input type="email" x-bind:name="'smtps[' + index + '][username]'" x-model="smtp.username" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">Password / App Password</label>
                                            <input type="password" x-bind:name="'smtps[' + index + '][password]'" x-model="smtp.password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        </div>

                                        <!-- Pacing Strategy & Limits -->
                                        <div class="pt-4 mt-4 border-t border-gray-200">
                                            <div class="mb-4">
                                                <label class="block text-xs font-medium text-gray-700 mb-2">Pacing Strategy</label>
                                                <div class="flex gap-4">
                                                    <label class="inline-flex items-center">
                                                        <input type="radio" x-bind:name="'smtps[' + index + '][pacing_strategy]'" value="per_hour" x-model="smtp.pacing_strategy" class="text-indigo-600 focus:ring-indigo-500">
                                                        <span class="ml-2 text-xs text-gray-700">Per Hour Pacing</span>
                                                    </label>
                                                    <label class="inline-flex items-center">
                                                        <input type="radio" x-bind:name="'smtps[' + index + '][pacing_strategy]'" value="per_day" x-model="smtp.pacing_strategy" class="text-indigo-600 focus:ring-indigo-500">
                                                        <span class="ml-2 text-xs text-gray-700">Per Day Pacing (Randomized)</span>
                                                    </label>
                                                </div>
                                            </div>

                                            <!-- Per Hour Strategy -->
                                            <div x-show="smtp.pacing_strategy === 'per_hour'" x-cloak class="space-y-3">
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-700">Hard Daily Limit</label>
                                                    <input type="number" x-bind:name="'smtps[' + index + '][daily_limit]'" x-model="smtp.daily_limit" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Leave blank for none">
                                                </div>
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-700">Min Emails Per Hour</label>
                                                        <input type="number" x-bind:name="'smtps[' + index + '][min_emails_per_hour]'" x-model="smtp.min_emails_per_hour" placeholder="e.g. 20" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-700">Max Emails Per Hour</label>
                                                        <input type="number" x-bind:name="'smtps[' + index + '][max_emails_per_hour]'" x-model="smtp.max_emails_per_hour" placeholder="e.g. 50" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Per Day Strategy -->
                                            <div x-show="smtp.pacing_strategy === 'per_day'" x-cloak class="space-y-3">
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-700">Min Emails Per Day</label>
                                                        <input type="number" x-bind:name="'smtps[' + index + '][min_emails_per_day]'" x-model="smtp.min_emails_per_day" placeholder="e.g. 400" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-700">Max Emails Per Day</label>
                                                        <input type="number" x-bind:name="'smtps[' + index + '][max_emails_per_day]'" x-model="smtp.max_emails_per_day" placeholder="e.g. 500" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </template>

                            <button type="button" @click="addSmtp()" class="mt-4 inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <svg class="-ml-1 mr-2 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                Add Another SMTP
                            </button>
                        </div>
                    </div> <!-- End Right Column -->
                </div>

                <div class="mt-6 flex items-center justify-end gap-3 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <a href="{{ route('admin.users.index') }}" class="text-gray-600 hover:text-gray-900 text-sm">Cancel</a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 transition" style="background-color: #4338CA;">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Create User
                    </button>
                </div>
            </form>
    </div>
</x-app-layout>
