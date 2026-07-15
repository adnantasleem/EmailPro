<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Add SMTP Server') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('smtp.store') }}">
                    @csrf

                    <!-- Name -->
                    <div class="mb-4">
                        <x-input-label for="name" :value="__('Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required placeholder="e.g., Gmail Account 1" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <!-- Host -->
                        <div>
                            <x-input-label for="host" :value="__('SMTP Host')" />
                            <x-text-input id="host" name="host" type="text" class="mt-1 block w-full" :value="old('host')" required placeholder="smtp.gmail.com" />
                            <x-input-error :messages="$errors->get('host')" class="mt-2" />
                        </div>

                        <!-- Port -->
                        <div>
                            <x-input-label for="port" :value="__('Port')" />
                            <x-text-input id="port" name="port" type="number" class="mt-1 block w-full" :value="old('port', 587)" required />
                            <x-input-error :messages="$errors->get('port')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <!-- Username -->
                        <div>
                            <x-input-label for="username" :value="__('Username')" />
                            <x-text-input id="username" name="username" type="text" class="mt-1 block w-full" :value="old('username')" required />
                            <x-input-error :messages="$errors->get('username')" class="mt-2" />
                        </div>

                        <!-- Password -->
                        <div>
                            <x-input-label for="password" :value="__('Password')" />
                            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>
                    </div>

                    <!-- Encryption -->
                    <div class="mb-4">
                        <x-input-label for="encryption" :value="__('Encryption')" />
                        <select id="encryption" name="encryption" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="tls" {{ old('encryption') == 'tls' ? 'selected' : '' }}>TLS</option>
                            <option value="ssl" {{ old('encryption') == 'ssl' ? 'selected' : '' }}>SSL</option>
                            <option value="none" {{ old('encryption') == 'none' ? 'selected' : '' }}>None</option>
                        </select>
                        <x-input-error :messages="$errors->get('encryption')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <!-- From Email -->
                        <div>
                            <x-input-label for="from_email" :value="__('From Email')" />
                            <x-text-input id="from_email" name="from_email" type="email" class="mt-1 block w-full" :value="old('from_email')" required />
                            <x-input-error :messages="$errors->get('from_email')" class="mt-2" />
                        </div>

                        <!-- From Name -->
                        <div>
                            <x-input-label for="from_name" :value="__('From Name')" />
                            <x-text-input id="from_name" name="from_name" type="text" class="mt-1 block w-full" :value="old('from_name')" required />
                            <x-input-error :messages="$errors->get('from_name')" class="mt-2" />
                        </div>
                    </div>

                    <!-- Pacing Strategy & Limits -->
                    <div class="mb-6 p-4 border border-gray-200 rounded-lg bg-gray-50" x-data="{ strategy: '{{ old('pacing_strategy', 'per_hour') }}' }">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Pacing Strategy</label>
                            <div class="flex gap-4">
                                <label class="inline-flex items-center">
                                    <input type="radio" name="pacing_strategy" value="per_hour" x-model="strategy" class="text-indigo-600 focus:ring-indigo-500">
                                    <span class="ml-2 text-sm text-gray-700">Per Hour Pacing</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="pacing_strategy" value="per_day" x-model="strategy" class="text-indigo-600 focus:ring-indigo-500">
                                    <span class="ml-2 text-sm text-gray-700">Per Day Pacing (Randomized)</span>
                                </label>
                            </div>
                        </div>

                        <!-- Per Hour Strategy -->
                        <div x-show="strategy === 'per_hour'" x-cloak class="space-y-4">
                            <div>
                                <x-input-label for="daily_limit" :value="__('Hard Daily Limit')" />
                                <x-text-input id="daily_limit" name="daily_limit" type="number" class="mt-1 block w-full" :value="old('daily_limit')" min="1" max="100000" />
                                <p class="mt-1 text-sm text-gray-500">Maximum total emails to send per day as a hard stop. Leave blank if you don't want a hard limit.</p>
                                <x-input-error :messages="$errors->get('daily_limit')" class="mt-2" />
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="min_emails_per_hour" :value="__('Min Emails Per Hour')" />
                                    <x-text-input id="min_emails_per_hour" name="min_emails_per_hour" type="number" class="mt-1 block w-full" :value="old('min_emails_per_hour')" placeholder="e.g., 20" min="1" max="100000" />
                                    <x-input-error :messages="$errors->get('min_emails_per_hour')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="max_emails_per_hour" :value="__('Max Emails Per Hour')" />
                                    <x-text-input id="max_emails_per_hour" name="max_emails_per_hour" type="number" class="mt-1 block w-full" :value="old('max_emails_per_hour')" placeholder="e.g., 50" min="1" max="100000" />
                                    <x-input-error :messages="$errors->get('max_emails_per_hour')" class="mt-2" />
                                </div>
                            </div>
                            <p class="text-sm text-gray-500">The system will randomize hourly sends between min and max.</p>
                        </div>

                        <!-- Per Day Strategy -->
                        <div x-show="strategy === 'per_day'" x-cloak class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="min_emails_per_day" :value="__('Min Emails Per Day')" />
                                    <x-text-input id="min_emails_per_day" name="min_emails_per_day" type="number" class="mt-1 block w-full" :value="old('min_emails_per_day')" placeholder="e.g., 400" min="1" max="100000" />
                                    <x-input-error :messages="$errors->get('min_emails_per_day')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="max_emails_per_day" :value="__('Max Emails Per Day')" />
                                    <x-text-input id="max_emails_per_day" name="max_emails_per_day" type="number" class="mt-1 block w-full" :value="old('max_emails_per_day')" placeholder="e.g., 500" min="1" max="100000" />
                                    <x-input-error :messages="$errors->get('max_emails_per_day')" class="mt-2" />
                                </div>
                            </div>
                            <p class="text-sm text-gray-500">The system will pick a random daily limit between min and max every day, and automatically divide it by 24 hours to pace your sending evenly.</p>
                        </div>
                    </div>

                    <!-- Active Time Window -->
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <x-input-label for="active_time_start" :value="__('Active Start Time')" />
                            <x-text-input id="active_time_start" name="active_time_start" type="time" class="mt-1 block w-full" :value="old('active_time_start')" />
                            <x-input-error :messages="$errors->get('active_time_start')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="active_time_end" :value="__('Active End Time')" />
                            <x-text-input id="active_time_end" name="active_time_end" type="time" class="mt-1 block w-full" :value="old('active_time_end')" />
                            <x-input-error :messages="$errors->get('active_time_end')" class="mt-2" />
                        </div>
                    </div>
                    <p class="mb-6 text-sm text-gray-500">Optional: Only send emails during this time window (e.g., 09:00 to 17:00). Leave blank to send 24/7.</p>

                    <!-- Active -->
                    <div class="mb-6">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ old('is_active', true) ? 'checked' : '' }}>
                            <span class="ms-2 text-sm text-gray-600">{{ __('Active (ready to use)') }}</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <button type="button" id="testSmtpBtn" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg id="testSpinner" class="hidden animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ __('Test Connection') }}
                        </button>
                        <div class="flex items-center gap-4">
                            <a href="{{ route('smtp.index') }}" class="text-gray-600 hover:text-gray-900">Cancel</a>
                            <x-primary-button>{{ __('Create SMTP') }}</x-primary-button>
                        </div>
                    </div>

                    <div id="testResult" class="mt-4 hidden p-4 rounded-md"></div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('testSmtpBtn').addEventListener('click', async function() {
            const btn = this;
            const spinner = document.getElementById('testSpinner');
            const resultDiv = document.getElementById('testResult');

            const data = {
                host: document.getElementById('host').value,
                port: document.getElementById('port').value,
                username: document.getElementById('username').value,
                password: document.getElementById('password').value,
                encryption: document.getElementById('encryption').value,
                from_email: document.getElementById('from_email').value,
                from_name: document.getElementById('from_name').value,
            };

            if (!data.host || !data.port || !data.username || !data.password || !data.from_email || !data.from_name) {
                resultDiv.className = 'mt-4 border-l-4 border-yellow-500 bg-yellow-50 p-4 rounded-md shadow-sm text-yellow-800';
                resultDiv.innerHTML = `<div class="flex items-start"><div class="flex-shrink-0"><svg class="h-5 w-5 text-yellow-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg></div><div class="ml-3"><p class="text-sm font-medium text-yellow-800">Please fill in all required fields first.</p></div></div>`;
                resultDiv.classList.remove('hidden');
                return;
            }

            btn.disabled = true;
            spinner.classList.remove('hidden');
            resultDiv.classList.add('hidden');

            try {
                const response = await fetch('{{ route("smtp.test-connection") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(data),
                });

                const result = await response.json();

                if (result.success) {
                    resultDiv.className = 'mt-4 border-l-4 border-green-500 bg-green-50 p-4 rounded-md shadow-sm text-green-800';
                    resultDiv.innerHTML = `<div class="flex items-start"><div class="flex-shrink-0"><svg class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg></div><div class="ml-3"><p class="text-sm font-medium text-green-800">${result.message}</p></div></div>`;
                } else {
                    resultDiv.className = 'mt-4 border-l-4 border-red-500 bg-red-50 p-4 rounded-md shadow-sm text-red-800';
                    resultDiv.innerHTML = `<div class="flex items-start"><div class="flex-shrink-0"><svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg></div><div class="ml-3"><p class="text-sm font-medium text-red-800 text-left break-words">${result.message}</p></div></div>`;
                }
            } catch (error) {
                resultDiv.className = 'mt-4 border-l-4 border-red-500 bg-red-50 p-4 rounded-md shadow-sm text-red-800';
                resultDiv.innerHTML = `<div class="flex items-start"><div class="flex-shrink-0"><svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg></div><div class="ml-3"><p class="text-sm font-medium text-red-800 text-left break-words">Error: ${error.message}</p></div></div>`;
            } finally {
                btn.disabled = false;
                spinner.classList.add('hidden');
                resultDiv.classList.remove('hidden');
            }
        });
    </script>
</x-app-layout>
