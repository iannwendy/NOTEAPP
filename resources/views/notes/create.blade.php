@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ __('Create New Note') }}</span>
                    <a href="{{ route('notes.index') }}" class="btn btn-secondary btn-sm">Back to Notes</a>
                </div>

                <div class="card-body">
                    @php
                        use Illuminate\Support\Facades\Auth;
                        // In ra giá trị màu để debug
                        $userPreferenceColor = Auth::user()->preferences['note_color'] ?? '#ffffff';
                        // Ưu tiên dùng giá trị từ controller, sau đó mới fallback về màu từ preferences
                        $defaultColor = $defaultColor ?? $userPreferenceColor;
                        error_log('Default note color from blade: ' . $defaultColor);
                    @endphp
                    
                    <!-- Hiển thị màu mặc định để debug -->
                    <div class="debug-info mb-3 d-none">
                        <div class="d-flex align-items-center">
                            <div style="width: 20px; height: 20px; background-color: {{ $defaultColor }}; margin-right: 5px; border: 1px solid #ccc;"></div>
                            <small>Default note color: <strong>{{ $defaultColor }}</strong></small>
                        </div>
                    </div>

                    <form id="noteForm" action="{{ route('notes.store') }}" method="POST">
                        @csrf
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title') }}" required autocomplete="off">
                            @error('title')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="content" class="form-label">Content</label>
                            <textarea class="form-control @error('content') is-invalid @enderror" id="content" name="content" rows="8" required>{{ old('content') }}</textarea>
                            @error('content')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        
                        <!-- Note Color field removed as per requirements -->
                        <input type="hidden" id="color" name="color" value="{{ $defaultColor }}">
                        
                        <!-- Hidden field to track temp_id for autosave -->
                        <input type="hidden" id="temp_id" name="temp_id" value="">

                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary">Save Note</button>
                            <div id="autoSaveSpinner" class="mt-2 d-none">
                                <div class="d-flex align-items-center">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <span id="autoSaveText">Auto-saving...</span>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden save status indicator -->
                        <div id="saveStatus" class="alert alert-info d-none mb-3" role="alert">
                            Saving...
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('noteForm');
        const titleInput = document.getElementById('title');
        const contentInput = document.getElementById('content');
        const saveStatus = document.getElementById('saveStatus');
        const autoSaveSpinner = document.getElementById('autoSaveSpinner');
        const autoSaveText = document.getElementById('autoSaveText');
        const tempIdField = document.getElementById('temp_id');
        const colorField = document.getElementById('color');
        
        // Debug info - In ra màu mặc định để kiểm tra
        console.log('Default note color loaded:', colorField.value);
        
        let typingTimer;
        let autoSaveTimer; // Timer for periodic auto-save
        const doneTypingInterval = 1000; // Save after 1 second of inactivity
        const autoSaveInterval = 5000; // Auto-save every 5 seconds
        let isDirty = false;
        let isSaving = false;
        let lastSavedTitle = '';
        let lastSavedContent = '';
        let currentNoteId = '';
        let autoSavePending = false;
        let noteCreated = false;
        let isAutosaving = false; // Track if autosave is in progress
        
        // Start periodic auto-save
        setupAutoSave();
        
        // Show initial message about auto-save
        showSaveStatus('Auto-save enabled - saving every 5 seconds', 'info');
        
        function showSaveStatus(message, type = 'info', isAutoSave = false) {
            saveStatus.textContent = message;
            saveStatus.classList.remove('d-none', 'alert-info', 'alert-success', 'alert-danger');
            saveStatus.classList.add(`alert-${type}`);
            
            // Auto-hide success messages after 3 seconds
            if (type === 'success') {
                setTimeout(() => {
                    saveStatus.classList.add('d-none');
                }, 3000);
                hideAutoSaveSpinner();
                isAutosaving = false;
            }
            
            if (isAutoSave) {
                autoSaveSpinner.classList.remove('d-none');
                autoSaveText.textContent = 'Auto-saving...';
                isAutosaving = true;
            }
        }
        
        function showAutoSaveSpinner(text) {
            autoSaveSpinner.classList.remove('d-none');
            if (text) {
                autoSaveText.textContent = text;
            }
            isAutosaving = true;
        }
        
        function hideAutoSaveSpinner() {
            setTimeout(() => {
                autoSaveSpinner.classList.add('d-none');
                isAutosaving = false;
            }, 1000);
        }
        
        // Function to setup periodic auto-save
        function setupAutoSave() {
            // Clear any existing timer
            if (autoSaveTimer) {
                clearInterval(autoSaveTimer);
            }
            
            // Set up periodic auto-save every 5 seconds
            autoSaveTimer = setInterval(() => {
                if (isDirty && !isSaving && !isAutosaving) {
                    console.log('Auto-saving on timer...');
                    saveNote();
                }
            }, autoSaveInterval);
            
            console.log('Periodic auto-save initialized - will save every ' + (autoSaveInterval/1000) + ' seconds');
        }
        
        async function saveNote() {
            if (isSaving) return;
            
            const title = titleInput.value.trim();
            const content = contentInput.value.trim();
            
            // Don't save if empty or unchanged
            if (!title || !content) {
                return;
            }
            
            if (title === lastSavedTitle && content === lastSavedContent && currentNoteId) {
                autoSavePending = false;
                return;
            }
            
            isSaving = true;
            isAutosaving = true;
            showAutoSaveSpinner('Auto-saving...');
            showSaveStatus('Saving...', 'info', true);
            
            try {
                const formData = new FormData();
                formData.append('title', title);
                formData.append('content', content);
                
                // Sử dụng màu từ trường ẩn, đây là màu mặc định từ preferences
                const noteColor = colorField.value;
                formData.append('color', noteColor);
                console.log('Using color for note:', noteColor);
                
                formData.append('_token', document.querySelector('input[name="_token"]').value);
                formData.append('_autosave', '1');
                
                // Add temp_id if we already have a note ID
                if (currentNoteId) {
                    formData.append('temp_id', currentNoteId);
                }
                
                const response = await fetch('{{ route('notes.store') }}', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('Server error response:', errorText);
                    throw new Error('Network response was not ok: ' + response.status);
                }
                
                // Check for content being JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const textResponse = await response.text();
                    console.error('Expected JSON but got:', contentType, textResponse.substring(0, 500));
                    throw new Error('Invalid response format. Expected JSON but got: ' + contentType);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    isDirty = false;
                    lastSavedTitle = title;
                    lastSavedContent = content;
                    currentNoteId = result.note.id;
                    
                    if (tempIdField) {
                        tempIdField.value = currentNoteId;
                    }
                    
                    noteCreated = true;
                    showSaveStatus('Note saved successfully!', 'success');
                } else {
                    showSaveStatus('Error: ' + (result.message || 'Failed to save'), 'danger');
                    hideAutoSaveSpinner();
                }
            } catch (error) {
                console.error('Save error:', error);
                showSaveStatus('Failed to save note: ' + error.message, 'danger');
                hideAutoSaveSpinner();
            } finally {
                isSaving = false;
                autoSavePending = false;
                // hideAutoSaveSpinner is already called on success
            }
        }
        
        function doneTyping() {
            if (isDirty && !isSaving && !isAutosaving) {
                autoSavePending = true;
                saveNote();
            }
        }
        
        // Set up event listeners for auto-saving
        titleInput.addEventListener('input', function() {
            isDirty = true;
            clearTimeout(typingTimer);
            typingTimer = setTimeout(doneTyping, doneTypingInterval);
            showSaveStatus('Unsaved changes...', 'info');
        });
        
        contentInput.addEventListener('input', function() {
            isDirty = true;
            clearTimeout(typingTimer);
            typingTimer = setTimeout(doneTyping, doneTypingInterval);
            showSaveStatus('Unsaved changes...', 'info');
        });
        
        // Save on blur events as well
        titleInput.addEventListener('blur', function() {
            if (isDirty && !isSaving && !isAutosaving) {
                saveNote();
            }
        });
        
        contentInput.addEventListener('blur', function() {
            if (isDirty && !isSaving && !isAutosaving) {
                saveNote();
            }
        });
        
        // Save before user navigates away
        window.addEventListener('beforeunload', function(e) {
            // Clear auto-save interval
            if (autoSaveTimer) {
                clearInterval(autoSaveTimer);
            }
            
            if (isDirty) {
                // Try to save
                saveNote();
                // Modern browsers no longer show custom messages, but we'll set one anyway
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });
        
        // Handle form submission
        form.addEventListener('submit', async function(e) {
            // Prevent default form submission in all cases
            e.preventDefault();
            
            // First, if there are any unsaved changes, save them
            if (isDirty) {
                // If there's an auto-save in progress, wait for it to complete
                if (autoSavePending || isSaving || isAutosaving) {
                    // Try to save now and cancel any pending timeout
                    if (typingTimer) {
                        clearTimeout(typingTimer);
                        typingTimer = null;
                    }
                    
                    // Mark as no longer pending auto-save since we're explicitly saving
                    autoSavePending = false;
                    
                    // Wait for saving to finish if it's in progress
                    if (isSaving || isAutosaving) {
                        showSaveStatus('Waiting for auto-save to complete...', 'info');
                        // Poll until isSaving is false
                        const waitForSave = () => {
                            if (isSaving || isAutosaving) {
                                setTimeout(waitForSave, 100);
                            } else {
                                finishSubmission();
                            }
                        };
                        waitForSave();
                    } else {
                        // If not currently saving, save now and then proceed
                        await saveNote();
                        finishSubmission();
                    }
                } else {
                    // Save now and then proceed
                    await saveNote();
                    finishSubmission();
                }
            } else {
                // No unsaved changes, proceed directly
                finishSubmission();
            }
            
            // Restart the auto-save timer
            setupAutoSave();
        });
        
        // Function to finish the form submission process
        function finishSubmission() {
            // If we have a note ID, redirect to it
            if (noteCreated && currentNoteId) {
                showSaveStatus('Note saved! Redirecting...', 'success');
                
                // Small delay before redirect to ensure user sees the success message
                setTimeout(() => {
                    window.location.href = '{{ route('notes.index') }}/' + currentNoteId;
                }, 500);
            } else {
                // If for some reason we don't have a note ID yet, submit the form normally
                // This should be rare since we've already tried to save
                showSaveStatus('Submitting form...', 'info');
                
                const formData = new FormData(form);
                
                fetch(form.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.redirected) {
                        window.location.href = response.url;
                    }
                })
                .catch(error => {
                    console.error('Error submitting form:', error);
                    showSaveStatus('Error submitting form: ' + error.message, 'danger');
                });
            }
        }
    });
</script>
@endpush
@endsection 