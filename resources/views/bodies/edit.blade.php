<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Edit Body Template') }}</h2>
    </x-slot>

    <!-- Summernote CSS -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">
    
    <style>
        .note-editor.note-frame {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
        }
        .note-editor.note-frame .note-editing-area .note-editable {
            background-color: #fff;
            min-height: 300px;
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
        .variable-btn-unsubscribe {
            background-color: #22c55e;
        }
        .variable-btn-unsubscribe:hover {
            background-color: #16a34a;
        }
    </style>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('bodies.update', $body) }}" id="templateForm">
                    @csrf @method('PUT')

                    <div class="mb-6">
                        <x-input-label for="name" :value="__('Template Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $body->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="mb-6">
                        <x-input-label for="body_group_id" :value="__('Group (Optional)')" />
                        <select name="body_group_id" id="body_group_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">— No Group —</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}" {{ old('body_group_id', $body->body_group_id) == $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Editor Section -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <x-input-label for="html_content" :value="__('Email Content')" />
                            </div>

                            <!-- Variable Insert Buttons -->
                            <div class="variable-buttons mb-3">
                                <span class="text-xs text-gray-500 mr-2">Insert Variable:</span>
                                <button type="button" class="variable-btn" data-var="@{{name}}">Name</button>
                                <button type="button" class="variable-btn" data-var="@{{first_name}}">First Name</button>
                                <button type="button" class="variable-btn" data-var="@{{email}}">Email</button>
                                <button type="button" class="variable-btn" data-var="@{{date}}">Date</button>
                                <button type="button" class="variable-btn" data-var="@{{year}}">Year</button>
                                <button type="button" class="variable-btn variable-btn-unsubscribe" data-var="unsubscribe">Unsubscribe Link</button>
                                <button type="button" class="variable-btn" style="background-color: #f59e0b;" onclick="insertCustomVariable()">+ Custom</button>
                            </div>

                            <!-- Summernote Editor -->
                            <textarea id="html_content" name="html_content" class="summernote-editor">{{ old('html_content', $body->html_content) }}</textarea>
                            <x-input-error :messages="$errors->get('html_content')" class="mt-2" />

                            <p class="text-xs text-gray-500 mt-2">Custom variables match column names from your contact list CSV</p>
                        </div>

                        <!-- Preview Section -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <x-input-label :value="__('Live Preview')" />
                                <span class="text-xs text-gray-500">Updates as you type</span>
                            </div>
                            <div id="preview" class="border border-gray-300 rounded-md p-4 min-h-[300px] bg-gray-50 overflow-auto" style="max-height: 400px;overflow-y: auto;">
                                <p class="text-gray-400 text-center">Loading preview...</p>
                            </div>
                            <div class="mt-2 p-2 bg-blue-50 rounded text-xs text-blue-700">
                                <strong>Preview sample data:</strong> Name: John Doe, Email: john@example.com
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 mb-6">
                        <x-input-label for="plain_content" :value="__('Plain Text Content (Optional)')" />
                        <textarea id="plain_content" name="plain_content" rows="4" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm font-mono text-sm">{{ old('plain_content', $body->plain_content) }}</textarea>
                        <x-input-error :messages="$errors->get('plain_content')" class="mt-2" />
                        <button type="button" onclick="generatePlainText()" class="mt-2 text-sm text-indigo-600 hover:text-indigo-800">Auto-generate from HTML →</button>
                    </div>

                    <div class="flex items-center justify-end gap-4">
                        <a href="{{ route('bodies.index') }}" class="text-gray-600 hover:text-gray-900">Cancel</a>
                        <x-primary-button>{{ __('Update Template') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- jQuery & Summernote JS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>

    <script>
    @verbatim
        const preview = document.getElementById('preview');

        // Sample data for preview
        const sampleData = {
            name: 'John Doe',
            first_name: 'John',
            email: 'john@example.com',
            company: 'Acme Inc',
            city: 'New York',
            date: new Date().toLocaleDateString(),
            year: new Date().getFullYear(),
            unsubscribe_link: '#unsubscribe'
        };

        $(document).ready(function() {
            // Initialize Summernote
            $('#html_content').summernote({
                height: 300,
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
                        updatePreview();
                    }
                }
            });

            // Variable insertion
            $('.variable-btn').on('click', function() {
                const variable = $(this).data('var');
                
                // Special handling for unsubscribe link
                if (variable === 'unsubscribe') {
                    const linkHtml = '<p style="margin-top: 20px; font-size: 12px; color: #666;">If you no longer wish to receive these emails, <a href="{{unsubscribe_link}}" style="color: #6366f1; text-decoration: underline;">click here to unsubscribe</a>.</p>';
                    $('#html_content').summernote('pasteHTML', linkHtml);
                } else {
                    $('#html_content').summernote('insertText', variable);
                }
                updatePreview();
            });

            // Initial preview update
            updatePreview();
        });

        function insertCustomVariable() {
            const varName = prompt('Enter custom variable name (e.g., company, city):');
            if (varName && varName.trim()) {
                const variable = '{{' + varName.trim().toLowerCase().replace(/\s+/g, '_') + '}}';
                $('#html_content').summernote('insertText', variable);
                updatePreview();
            }
        }

        function updatePreview() {
            let content = $('#html_content').summernote('code');
            
            // Replace variables with sample data
            for (const [key, value] of Object.entries(sampleData)) {
                content = content.replace(new RegExp('\\{\\{' + key + '\\}\\}', 'gi'), value);
            }
            
            // Replace any remaining unknown variables with placeholder
            content = content.replace(/\{\{(\w+)\}\}/g, '<span style="background:#fef3c7;padding:0 4px;border-radius:2px;">[$1]</span>');
            
            if (content.trim() && content.trim() !== '<p><br></p>') {
                preview.innerHTML = content;
            } else {
                preview.innerHTML = '<p class="text-gray-400 text-center">Start typing to see preview...</p>';
            }
        }

        function generatePlainText() {
            const html = $('#html_content').summernote('code');
            // Simple HTML to plain text conversion
            const temp = document.createElement('div');
            temp.innerHTML = html;
            let text = temp.textContent || temp.innerText || '';
            text = text.replace(/\s+/g, ' ').trim();
            document.getElementById('plain_content').value = text;
        }

        // Before form submit, ensure hidden field is updated
        document.getElementById('templateForm').addEventListener('submit', function() {
            // Summernote auto-syncs, but let's make sure
            $('#html_content').val($('#html_content').summernote('code'));
        });
    @endverbatim
    </script>
</x-app-layout>
