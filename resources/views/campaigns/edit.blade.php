<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Campaign') }}: {{ $campaign->name }}
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
                <form method="POST" action="{{ route('campaigns.update', $campaign) }}" id="campaignForm">
                    @csrf
                    @method('PUT')

                    <!-- Campaign Name -->
                    <div class="mb-6">
                        <x-input-label for="name" :value="__('Campaign Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $campaign->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <!-- From Name & Reply Email -->
                    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="from_name" :value="__('From Name')" />
                            <x-text-input id="from_name" name="from_name" type="text" class="mt-1 block w-full" :value="old('from_name', $campaign->from_name)" placeholder="e.g., John from Company" />
                            <p class="mt-1 text-sm text-gray-500">Leave empty to use SMTP default</p>
                            <x-input-error :messages="$errors->get('from_name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="reply_to" :value="__('Reply-To Email')" />
                            <x-text-input id="reply_to" name="reply_to" type="email" class="mt-1 block w-full" :value="old('reply_to', $campaign->reply_to)" placeholder="e.g., reply@example.com" />
                            <p class="mt-1 text-sm text-gray-500">Leave empty to use From email</p>
                            <x-input-error :messages="$errors->get('reply_to')" class="mt-2" />
                        </div>
                    </div>

                    <!-- Rate Limiting -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Rate Limiting & Delays</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <x-input-label for="emails_per_hour" :value="__('Emails Per Hour')" />
                                <x-text-input id="emails_per_hour" name="emails_per_hour" type="number" class="mt-1 block w-full" :value="old('emails_per_hour', $campaign->emails_per_hour)" required min="1" max="10000" />
                                <x-input-error :messages="$errors->get('emails_per_hour')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="min_delay_seconds" :value="__('Min Delay (seconds)')" />
                                <x-text-input id="min_delay_seconds" name="min_delay_seconds" type="number" class="mt-1 block w-full" :value="old('min_delay_seconds', $campaign->min_delay_seconds)" required min="0" max="300" />
                                <x-input-error :messages="$errors->get('min_delay_seconds')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="max_delay_seconds" :value="__('Max Delay (seconds)')" />
                                <x-text-input id="max_delay_seconds" name="max_delay_seconds" type="number" class="mt-1 block w-full" :value="old('max_delay_seconds', $campaign->max_delay_seconds)" required min="0" max="300" />
                                <x-input-error :messages="$errors->get('max_delay_seconds')" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <!-- Schedule -->
                    <div class="mb-6">
                        <x-input-label for="scheduled_at" :value="__('Schedule (Optional)')" />
                        <x-text-input id="scheduled_at" name="scheduled_at" type="datetime-local" class="mt-1 block w-full" :value="old('scheduled_at', $campaign->scheduled_at?->format('Y-m-d\TH:i'))" />
                        <x-input-error :messages="$errors->get('scheduled_at')" class="mt-2" />
                    </div>

                    <!-- Subject Lines -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Subject Lines</h3>
                        @if($subjectGroups->count() > 0)
                            <div class="mb-4">
                                <x-input-label :value="__('Add from Subject Groups')" />
                                <select name="saved_subject_groups[]" id="saved_subject_groups" multiple class="mt-1 block w-full">
                                    @foreach($subjectGroups as $group)
                                        <option value="{{ $group->id }}">{{ $group->name }} ({{ $group->subject_lines_count }} subjects)</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500">All subjects in the selected groups will be added.</p>
                            </div>
                            <div class="relative my-4">
                                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                                <div class="relative flex justify-center text-sm"><span class="px-2 bg-gray-50 text-gray-500">And/or select individual</span></div>
                            </div>
                        @endif
                        @if($savedSubjectLines->count() > 0)
                            <div class="mb-4">
                                <x-input-label :value="__('Add from Saved Subject Lines')" />
                                <select name="saved_subjects[]" id="saved_subjects" multiple class="mt-1 block w-full">
                                    @foreach($savedSubjectLines as $subj)
                                        <option value="{{ $subj->id }}">{{ $subj->subject }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-sm text-gray-500">Select saved subjects to add to this campaign</p>
                            </div>
                            <div class="relative my-4">
                                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                                <div class="relative flex justify-center text-sm"><span class="px-2 bg-gray-50 text-gray-500">Current subjects</span></div>
                            </div>
                        @endif
                        <div id="subjectsContainer" class="space-y-3">
                            @foreach($subjectLines as $index => $subject)
                                <div class="flex gap-2 items-center subject-row">
                                    <input type="hidden" name="subject_ids[]" value="{{ $subject->id }}">
                                    <input name="subjects[]" type="text" value="{{ old('subjects.'.$index, $subject->subject) }}" class="flex-1 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" placeholder="Enter subject line..." />
                                    <span class="text-xs text-gray-500 whitespace-nowrap">{{ $subject->usage_count }} uses</span>
                                    <button type="button" class="px-3 py-2 text-red-600 hover:text-red-900 remove-subject {{ count($subjectLines) <= 1 ? 'hidden' : '' }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" id="addSubjectBtn" class="mt-3 text-sm text-indigo-600 hover:text-indigo-900">+ Add Subject Line</button>
                        <x-input-error :messages="$errors->get('subjects')" class="mt-2" />
                    </div>

                    <!-- Body Templates -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Body Templates</h3>
                        @if($bodyGroups->count() > 0)
                            <div class="mb-4">
                                <x-input-label :value="__('Add from Body Groups')" />
                                <select name="saved_body_groups[]" id="saved_body_groups" multiple class="mt-1 block w-full">
                                    @foreach($bodyGroups as $group)
                                        <option value="{{ $group->id }}">{{ $group->name }} ({{ $group->body_templates_count }} templates)</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500">All templates in the selected groups will be added.</p>
                            </div>
                            <div class="relative my-4">
                                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                                <div class="relative flex justify-center text-sm"><span class="px-2 bg-gray-50 text-gray-500">And/or select individual</span></div>
                            </div>
                        @endif
                        @if($savedBodyTemplates->count() > 0)
                            <div class="mb-4">
                                <x-input-label :value="__('Add from Saved Body Templates')" />
                                <select name="saved_bodies[]" id="saved_bodies" multiple class="mt-1 block w-full">
                                    @foreach($savedBodyTemplates as $body)
                                        <option value="{{ $body->id }}">{{ $body->name ?? 'Template #' . $body->id }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-sm text-gray-500">Select saved templates to add to this campaign</p>
                            </div>
                            <div class="relative my-4">
                                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                                <div class="relative flex justify-center text-sm"><span class="px-2 bg-gray-50 text-gray-500">Current templates</span></div>
                            </div>
                        @endif
                        <div id="bodiesContainer" class="space-y-4">
                            @foreach($bodyTemplates as $index => $body)
                                <div class="p-4 bg-white rounded border body-template-row">
                                    <input type="hidden" name="body_ids[]" value="{{ $body->id }}">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-sm font-medium text-gray-700">Template #{{ $index + 1 }}</span>
                                        <div class="flex items-center gap-3">
                                            <span class="text-xs text-gray-500">{{ $body->usage_count }} uses</span>
                                            <button type="button" class="text-red-600 hover:text-red-900 text-sm remove-body {{ count($bodyTemplates) <= 1 ? 'hidden' : '' }}">Remove</button>
                                        </div>
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
                                        <textarea name="bodies[{{ $index }}][html]" class="summernote-editor">{{ old('bodies.'.$index.'.html', $body->html_content) }}</textarea>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Plain Text (Optional)</label>
                                        <textarea name="bodies[{{ $index }}][plain]" rows="3" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm plain-text-area" placeholder="Plain text version...">{{ old('bodies.'.$index.'.plain', $body->plain_content) }}</textarea>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" id="addBodyBtn" class="mt-3 text-sm text-indigo-600 hover:text-indigo-900">+ Add Body Template</button>
                        <p class="mt-2 text-sm text-gray-500">Use the variable buttons to insert personalization fields.</p>
                        <x-input-error :messages="$errors->get('bodies')" class="mt-2" />
                    </div>

                    <!-- Contact Lists Selection -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Contact Lists</h3>
                        @if(isset($contactLists) && $contactLists->count() > 0)
                            <select name="contact_lists[]" id="contact_lists" multiple class="mt-1 block w-full">
                                @foreach($contactLists as $list)
                                    <option value="{{ $list->id }}" {{ in_array($list->id, old('contact_lists', $selectedContactLists ?? [])) ? 'selected' : '' }}>
                                        {{ $list->name }} ({{ $list->contacts_count }} contacts)
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-2 text-sm text-gray-500">Update the contact lists for this campaign.</p>
                        @else
                            <p class="text-gray-500 text-sm">No contact lists available.</p>
                        @endif
                        <x-input-error :messages="$errors->get('contact_lists')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end gap-4">
                        <a href="{{ route('campaigns.show', $campaign) }}" class="text-gray-600 hover:text-gray-900">Cancel</a>
                        <x-primary-button>{{ __('Update Campaign') }}</x-primary-button>
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
        let bodyTemplateCount = {{ count($bodyTemplates) }};

        function initSummernote(element) {
            $(element).summernote({
                height: 250,
                placeholder: 'Compose your email here...',
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
                $(this).find('.text-sm.font-medium').first().text('Template #' + (index + 1));
            });
        }

        $(document).ready(function() {
            // Initialize Summernote for existing editors
            $('.summernote-editor').each(function() {
                initSummernote(this);
            });

            // Initialize Select2 for all multi-selects
            $('#contact_lists').select2({
                placeholder: 'Search and select contact lists...',
                allowClear: true,
                width: '100%'
            });

            $('#saved_subjects').select2({
                placeholder: 'Select saved subject lines to add...',
                allowClear: true,
                width: '100%'
            });

            $('#saved_subject_groups').select2({
                placeholder: 'Select subject groups to add...',
                allowClear: true,
                width: '100%'
            });

            $('#saved_body_groups').select2({
                placeholder: 'Select body groups to add...',
                allowClear: true,
                width: '100%'
            });

            $('#saved_bodies').select2({
                placeholder: 'Select saved body templates to add...',
                allowClear: true,
                width: '100%'
            });

            // Add Subject Line
            $('#addSubjectBtn').on('click', function() {
                const newRow = `
                    <div class="flex gap-2 items-center subject-row">
                        <input type="hidden" name="subject_ids[]" value="">
                        <input name="subjects[]" type="text" class="flex-1 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" placeholder="Enter new subject line..." />
                        <span class="text-xs text-gray-500 whitespace-nowrap">New</span>
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
                        <input type="hidden" name="body_ids[]" value="">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium text-gray-700">Template #${bodyTemplateCount + 1}</span>
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-gray-500">New</span>
                                <button type="button" class="text-red-600 hover:text-red-900 text-sm remove-body">Remove</button>
                            </div>
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
                            <label class="block text-xs text-gray-500 mb-1">Plain Text (Optional)</label>
                            <textarea name="bodies[${bodyTemplateCount}][plain]" rows="3" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm plain-text-area" placeholder="Plain text version..."></textarea>
                        </div>
                    </div>
                `;
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
    </script>
</x-app-layout>

