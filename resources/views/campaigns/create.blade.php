<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Campaign') }}
        </h2>
    </x-slot>

    <!-- Summernote CSS -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">
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
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
            color: #f87171;
        }
        .note-editor.note-frame {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
        }
        .note-editor.note-frame .note-editing-area .note-editable {
            background-color: #fff;
            min-height: 200px;
        }
        .variable-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }
        .variable-btn {
            background-color: #6366f1;
            color: white;
            border: none;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .variable-btn:hover {
            background-color: #4f46e5;
        }
    </style>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('campaigns.store') }}" id="campaignForm">
                    @csrf

                    <!-- Campaign Name -->
                    <div class="mb-6">
                        <x-input-label for="name" :value="__('Campaign Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required placeholder="e.g., January Newsletter" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <!-- From Name & Reply Email -->
                    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="from_name" :value="__('From Name')" />
                            <x-text-input id="from_name" name="from_name" type="text" class="mt-1 block w-full" :value="old('from_name')" placeholder="e.g., John from Company" />
                            <p class="mt-1 text-sm text-gray-500">Leave empty to use SMTP default</p>
                            <x-input-error :messages="$errors->get('from_name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="reply_to" :value="__('Reply-To Email')" />
                            <x-text-input id="reply_to" name="reply_to" type="email" class="mt-1 block w-full" :value="old('reply_to')" placeholder="e.g., reply@example.com" />
                            <p class="mt-1 text-sm text-gray-500">Leave empty to use From email</p>
                            <x-input-error :messages="$errors->get('reply_to')" class="mt-2" />
                        </div>
                    </div>



                    <!-- Schedule -->
                    <div class="mb-6">
                        <x-input-label for="scheduled_at" :value="__('Schedule (Optional)')" />
                        <x-text-input id="scheduled_at" name="scheduled_at" type="datetime-local" class="mt-1 block w-full" :value="old('scheduled_at')" />
                        <p class="mt-1 text-sm text-gray-500">Leave empty to start immediately after validation.</p>
                        <x-input-error :messages="$errors->get('scheduled_at')" class="mt-2" />
                    </div>

                    <!-- Subject Lines -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Subject Lines</h3>
                        @if($subjectGroups->count() > 0)
                            <div class="mb-4">
                                <x-input-label :value="__('Select Subject Groups')" />
                                <select name="saved_subject_groups[]" id="saved_subject_groups" multiple class="mt-1 block w-full">
                                    @foreach($subjectGroups as $group)
                                        <option value="{{ $group->id }}">{{ $group->name }} ({{ $group->subject_lines_count }} subjects)</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500">All subjects in the selected groups will be included.</p>
                            </div>
                            <div class="relative my-4">
                                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                                <div class="relative flex justify-center text-sm"><span class="px-2 bg-gray-50 text-gray-500">And/or select individual</span></div>
                            </div>
                        @endif
                        @if($subjectLines->count() > 0)
                            <div class="mb-4">
                                <x-input-label :value="__('Select Saved Subject Lines')" />
                                <select name="saved_subjects[]" id="saved_subjects" multiple class="mt-1 block w-full">
                                    @foreach($subjectLines as $subj)
                                        <option value="{{ $subj->id }}">{{ $subj->subject }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="relative my-4">
                                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                                <div class="relative flex justify-center text-sm"><span class="px-2 bg-gray-50 text-gray-500">Or add new</span></div>
                            </div>
                        @endif
                        <div class="flex justify-between items-center mb-2">
                            <x-input-label :value="__('Add New Subject Lines')" />
                            <button type="button" id="addSubjectBtn" class="text-sm text-indigo-600 hover:text-indigo-900">+ Add</button>
                        </div>
                        <div id="subjectsContainer" class="space-y-3">
                            <div class="flex gap-2 subject-row">
                                <input name="subjects[]" type="text" class="flex-1 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" placeholder="Enter subject line..." />
                                <button type="button" class="px-3 py-2 text-red-600 hover:text-red-900 remove-subject hidden">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <p class="mt-2 text-sm text-gray-500">Select saved subjects and/or add new ones.</p>
                        <x-input-error :messages="$errors->get('subjects')" class="mt-2" />
                    </div>

                    <!-- Body Templates -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Email Body Templates</h3>
                        @if($bodyGroups->count() > 0)
                            <div class="mb-4">
                                <x-input-label :value="__('Select Body Groups')" />
                                <select name="saved_body_groups[]" id="saved_body_groups" multiple class="mt-1 block w-full">
                                    @foreach($bodyGroups as $group)
                                        <option value="{{ $group->id }}">{{ $group->name }} ({{ $group->body_templates_count }} templates)</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500">All templates in the selected groups will be included.</p>
                            </div>
                            <div class="relative my-4">
                                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                                <div class="relative flex justify-center text-sm"><span class="px-2 bg-gray-50 text-gray-500">And/or select individual</span></div>
                            </div>
                        @endif
                        @if($bodyTemplates->count() > 0)
                            <div class="mb-4">
                                <x-input-label :value="__('Select Saved Body Templates')" />
                                <select name="saved_bodies[]" id="saved_bodies" multiple class="mt-1 block w-full">
                                    @foreach($bodyTemplates as $body)
                                        <option value="{{ $body->id }}">{{ $body->name ?? 'Template #' . $body->id }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="relative my-4">
                                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                                <div class="relative flex justify-center text-sm"><span class="px-2 bg-gray-50 text-gray-500">Or add new</span></div>
                            </div>
                        @endif
                        <div class="flex justify-between items-center mb-2">
                            <x-input-label :value="__('Add New Body Templates')" />
                            <button type="button" id="addBodyBtn" class="text-sm text-indigo-600 hover:text-indigo-900">+ Add Template</button>
                        </div>
                        <div id="bodiesContainer" class="space-y-4">
                            @if($bodyTemplates->count() == 0)
                            {{-- Only show default body template if no saved bodies exist --}}
                            <div class="p-4 bg-white rounded border body-template-row">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm font-medium text-gray-700">Template #1</span>
                                    <button type="button" class="text-red-600 hover:text-red-900 text-sm remove-body hidden">Remove</button>
                                </div>
                                <!-- Variable Insert Buttons -->
                                <div class="variable-buttons mb-3">
                                    <span class="text-xs text-gray-500 mr-2">Insert Variable:</span>
                                    <button type="button" class="variable-btn" data-var="@{{ name }}">Name</button>
                                    <button type="button" class="variable-btn" data-var="@{{ first_name }}">First Name</button>
                                    <button type="button" class="variable-btn" data-var="@{{ email }}">Email</button>
                                    <button type="button" class="variable-btn" data-var="@{{ date }}">Date</button>
                                    <button type="button" class="variable-btn" data-var="@{{ year }}">Year</button>
                                    <button type="button" class="variable-btn variable-btn-link" data-var="unsubscribe" style="background-color: #dc2626;">Unsubscribe Link</button>
                                </div>
                                <div class="mb-2">
                                    <label class="block text-xs text-gray-500 mb-1">HTML Content (WYSIWYG Editor)</label>
                                    <textarea name="bodies[0][html]" class="summernote-editor" placeholder="Compose your email here..."></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Plain Text (Optional - auto-generated if empty)</label>
                                    <textarea name="bodies[0][plain]" rows="3" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm plain-text-area" placeholder="Plain text version..."></textarea>
                                </div>
                            </div>
                            @else
                            {{-- If saved bodies exist, show empty container - user can add manually if needed --}}
                            <p class="text-sm text-gray-500 italic" id="noNewBodiesText">Select saved templates above, or click "Add Template" to create new ones.</p>
                            @endif
                        </div>
                        <p class="mt-3 text-sm text-gray-500">Use the variable buttons to insert personalization fields. Select saved templates and/or add new ones.</p>
                        <x-input-error :messages="$errors->get('bodies')" class="mt-2" />
                    </div>

                    <!-- SMTP Selection -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg"
                         x-data="smtpSelector({{ json_encode($smtpConfigs ?? []) }}, {{ json_encode(old('smtp_configs', [])) }}, {{ old('use_all_smtps', true) ? 'true' : 'false' }})">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">SMTP Configuration</h3>
                        
                        <div class="mb-4">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="use_all_smtps" id="use_all_smtps" value="1" x-model="useAllSmtps" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Use all my active SMTP accounts (Recommended)</span>
                            </label>
                        </div>
                        
                        <div x-show="!useAllSmtps" x-cloak class="mt-4">
                            <x-input-label :value="__('Select Specific SMTP Accounts')" />
                            
                            <!-- Trigger Button -->
                            <button type="button" @click="isOpen = true" class="mt-1 w-full bg-white border border-gray-300 rounded-md shadow-sm pl-3 pr-10 py-2 text-left cursor-default focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm flex justify-between items-center">
                                <span class="block truncate" x-text="selectedSmtpsCount > 0 ? selectedSmtpsCount + ' SMTPs selected' : 'Select SMTP accounts...'"></span>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                </span>
                            </button>
                            <p class="mt-1 text-xs text-gray-500">Only the selected SMTP accounts will be used for this campaign.</p>
                            <x-input-error :messages="$errors->get('smtp_configs')" class="mt-2" />

                            <!-- Hidden Inputs -->
                            <template x-for="smtp in smtps.filter(s => s.checked)" :key="smtp.id">
                                <input type="hidden" name="smtp_configs[]" :value="smtp.id">
                            </template>

                            <!-- Modal -->
                            <div x-show="isOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                                    <!-- Background backdrop -->
                                    <div x-show="isOpen" @click="isOpen = false" x-transition.opacity class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                                    
                                    <!-- Modal panel -->
                                    <div x-show="isOpen" x-transition class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                            <div class="flex justify-between items-center mb-4">
                                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">Select SMTP Accounts</h3>
                                                <button @click="isOpen = false" type="button" class="text-gray-400 hover:text-gray-500">
                                                    <span class="sr-only">Close</span>
                                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                </button>
                                            </div>
                                            
                                            <!-- Search -->
                                            <div class="mb-4 relative">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                                </div>
                                                <input type="text" x-model="search" placeholder="Search by name or username..." class="pl-10 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                            </div>

                                            <!-- Controls -->
                                            <div class="flex justify-between items-center mb-2 px-2">
                                                <span class="text-sm text-gray-500" x-text="filteredSmtps.length + ' accounts found'"></span>
                                                <button type="button" @click="toggleAll" class="text-sm text-indigo-600 hover:text-indigo-900 font-medium" x-text="allFilteredChecked ? 'Uncheck All' : 'Check All'"></button>
                                            </div>

                                            <!-- List -->
                                            <div class="max-h-60 overflow-y-auto border border-gray-200 rounded-md">
                                                <template x-for="smtp in filteredSmtps" :key="smtp.id">
                                                    <label class="flex items-center px-4 py-3 border-b border-gray-100 hover:bg-gray-50 cursor-pointer last:border-b-0">
                                                        <input type="checkbox" x-model="smtp.checked" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                                        <div class="ml-3">
                                                            <span class="block text-sm font-medium text-gray-700" x-text="smtp.name"></span>
                                                            <span class="block text-xs text-gray-500" x-text="smtp.username"></span>
                                                        </div>
                                                    </label>
                                                </template>
                                                <div x-show="filteredSmtps.length === 0" class="px-4 py-8 text-center text-gray-500 text-sm">
                                                    No SMTP accounts match your search.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="bg-gray-50 px-4 py-3 sm:px-6 flex justify-end">
                                            <button type="button" @click="isOpen = false" class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm">
                                                Done
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Lists Selection -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Select Contact Lists</h3>
                        @if($contactLists->count() > 0)
                            <select name="contact_lists[]" id="contact_lists" multiple class="mt-1 block w-full">
                                @foreach($contactLists as $list)
                                    <option value="{{ $list->id }}" {{ in_array($list->id, old('contact_lists', [])) ? 'selected' : '' }}>
                                        {{ $list->name }} ({{ $list->contacts_count }} contacts)
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <p class="text-gray-500 text-sm">No contact lists yet. <a href="{{ route('contact-lists.create') }}" class="text-indigo-600 hover:underline">Create one</a></p>
                        @endif
                        <x-input-error :messages="$errors->get('contact_lists')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end gap-4">
                        <a href="{{ route('campaigns.index') }}" class="text-gray-600 hover:text-gray-900">Cancel</a>
                        <x-primary-button>{{ __('Create Campaign') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- jQuery & Summernote JS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        let bodyTemplateCount = 1;

        function initSummernote(element) {
            $(element).summernote({
                height: 250,
                placeholder: 'Compose your email here... Use the variable buttons above to personalize.',
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'italic', 'clear']],
                    ['fontname', ['fontname']],
                    ['fontsize', ['fontsize']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture', 'video']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ],
                callbacks: {
                    onChange: function(contents) {
                        $(this).val(contents);
                    }
                }
            });
        }

        function updateRemoveButtons() {
            const subjectRows = $('#subjectsContainer .subject-row');
            subjectRows.each(function(index) {
                $(this).find('.remove-subject').toggleClass('hidden', subjectRows.length <= 1);
            });

            const bodyRows = $('#bodiesContainer .body-template-row');
            bodyRows.each(function(index) {
                $(this).find('.remove-body').toggleClass('hidden', bodyRows.length <= 1);
                $(this).find('.text-sm.font-medium').text('Template #' + (index + 1));
            });
        }

        $(document).ready(function() {
            // Initialize Summernote for existing editors
            $('.summernote-editor').each(function() {
                initSummernote(this);
            });

            // Initialize Select2
            $('#contact_lists').select2({
                placeholder: 'Search and select contact lists...',
                allowClear: true,
                width: '100%'
            });
            $('#saved_subject_groups').select2({
                placeholder: 'Search and select subject groups...',
                allowClear: true,
                width: '100%'
            });
            $('#saved_subjects').select2({
                placeholder: 'Search and select subject lines...',
                allowClear: true,
                width: '100%'
            });
            $('#saved_body_groups').select2({
                placeholder: 'Search and select body groups...',
                allowClear: true,
                width: '100%'
            });
            $('#saved_bodies').select2({
                placeholder: 'Search and select body templates...',
                allowClear: true,
                width: '100%'
            });

            // Add Subject Line
            $('#addSubjectBtn').on('click', function() {
                const newRow = `
                    <div class="flex gap-2 subject-row">
                        <input name="subjects[]" type="text" class="flex-1 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" placeholder="Enter subject line..." />
                        <button type="button" class="px-3 py-2 text-red-600 hover:text-red-900 remove-subject">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                `;
                $('#subjectsContainer').append(newRow);
                updateRemoveButtons();
            });

            // Remove Subject Line
            $(document).on('click', '.remove-subject', function() {
                $(this).closest('.subject-row').remove();
                updateRemoveButtons();
            });

            // Add Body Template
            $('#addBodyBtn').on('click', function() {
                const newRow = `
                    <div class="p-4 bg-white rounded border body-template-row">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium text-gray-700">Template #${bodyTemplateCount + 1}</span>
                            <button type="button" class="text-red-600 hover:text-red-900 text-sm remove-body">Remove</button>
                        </div>
                        <div class="variable-buttons mb-3">
                            <span class="text-xs text-gray-500 mr-2">Insert Variable:</span>
                            <button type="button" class="variable-btn" data-var="@{{ name }}">Name</button>
                            <button type="button" class="variable-btn" data-var="@{{ first_name }}">First Name</button>
                            <button type="button" class="variable-btn" data-var="@{{ email }}">Email</button>
                            <button type="button" class="variable-btn" data-var="@{{ date }}">Date</button>
                            <button type="button" class="variable-btn" data-var="@{{ year }}">Year</button>
                            <button type="button" class="variable-btn variable-btn-link" data-var="unsubscribe" style="background-color: #dc2626;">Unsubscribe Link</button>
                        </div>
                        <div class="mb-2">
                            <label class="block text-xs text-gray-500 mb-1">HTML Content (WYSIWYG Editor)</label>
                            <textarea name="bodies[${bodyTemplateCount}][html]" class="summernote-editor-new" placeholder="Compose your email here..."></textarea>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Plain Text (Optional - auto-generated if empty)</label>
                            <textarea name="bodies[${bodyTemplateCount}][plain]" rows="3" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm plain-text-area" placeholder="Plain text version..."></textarea>
                        </div>
                    </div>
                `;
                $('#noNewBodiesText').hide(); // Hide the "no new bodies" text
                $('#bodiesContainer').append(newRow);
                
                // Initialize Summernote for new editor
                initSummernote($('#bodiesContainer .summernote-editor-new').last());
                $('#bodiesContainer .summernote-editor-new').last().removeClass('summernote-editor-new').addClass('summernote-editor');
                
                bodyTemplateCount++;
                updateRemoveButtons();
            });

            // Remove Body Template
            $(document).on('click', '.remove-body', function() {
                const row = $(this).closest('.body-template-row');
                row.find('.summernote-editor').summernote('destroy');
                row.remove();
                updateRemoveButtons();
            });

            // Variable insertion
            $(document).on('click', '.variable-btn', function() {
                const variable = $(this).data('var');
                const editor = $(this).closest('.body-template-row').find('.summernote-editor');
                
                // Special handling for unsubscribe link - insert a full HTML link
                if (variable === 'unsubscribe') {
                    const linkHtml = '<p style="margin-top: 20px; font-size: 12px; color: #666;">If you no longer wish to receive these emails, <a href="@{{ unsubscribe_link }}" style="color: #6366f1; text-decoration: underline;">click here to unsubscribe</a>.</p>';
                    editor.summernote('pasteHTML', linkHtml);
                } else {
                    editor.summernote('insertText', variable);
                }
            });

            // Form submission - sync Summernote content
            $('#campaignForm').on('submit', function() {
                $('.summernote-editor').each(function() {
                    $(this).val($(this).summernote('code'));
                });
            });
        });

        document.addEventListener('alpine:init', () => {
            Alpine.data('smtpSelector', (allSmtps, selectedIds, initUseAllSmtps) => ({
                isOpen: false,
                useAllSmtps: initUseAllSmtps,
                search: '',
                smtps: allSmtps.map(s => ({
                    ...s,
                    checked: selectedIds.includes(s.id.toString()) || selectedIds.includes(s.id)
                })),
                get filteredSmtps() {
                    if (this.search === '') return this.smtps;
                    const q = this.search.toLowerCase();
                    return this.smtps.filter(s => 
                        (s.name && s.name.toLowerCase().includes(q)) || 
                        (s.username && s.username.toLowerCase().includes(q))
                    );
                },
                get allFilteredChecked() {
                    if (this.filteredSmtps.length === 0) return false;
                    return this.filteredSmtps.every(s => s.checked);
                },
                get selectedSmtpsCount() {
                    return this.smtps.filter(s => s.checked).length;
                },
                toggleAll() {
                    const check = !this.allFilteredChecked;
                    this.filteredSmtps.forEach(s => s.checked = check);
                }
            }));
        });
    </script>
</x-app-layout>

