@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div id="offlineStatusContainer" class="d-none mb-3">
                <div class="alert alert-warning">
                    <i class="fas fa-wifi-slash me-2"></i> You're viewing this note in offline mode. Some features may be limited.
                </div>
            </div>
            
            <div class="card note-card" style="background-color: {{ $note->color ?? '#ffffff' }}; color: {{ in_array($note->color, ['#212529', '#343a40', '#495057', '#000000', '#111111', '#222222', '#333333']) ? '#ffffff' : '#000000' }};">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <span>{{ $note->title }}</span>
                        @isset($isShared)
                            @if($isShared)
                                <span class="badge {{ $permission === 'read' ? 'bg-info' : 'bg-warning' }} ms-2">
                                    <i class="fas {{ $permission === 'read' ? 'fa-eye' : 'fa-edit' }} me-1"></i>
                                    {{ $permission === 'read' ? 'Read-only' : 'Can edit' }}
                                </span>
                            @endif
                        @endisset
                    </div>
                    <div>
                        @isset($isOwner)
                            @if($isOwner)
                                <a href="{{ route('notes.shares.show', $note) }}" class="btn btn-primary btn-sm">
                                    <i class="fas fa-share-alt me-1"></i> Sharing
                                </a>
                                <a href="{{ route('notes.edit', $note) }}" class="btn btn-warning btn-sm">Edit</a>
                                <a href="{{ route('notes.password-protection', $note) }}" class="btn btn-info btn-sm">
                                    <i class="fas {{ $note->is_password_protected ? 'fa-lock' : 'fa-unlock' }} me-1"></i>
                                    {{ $note->is_password_protected ? 'Change Password' : 'Set Password' }}
                                </a>
                                <form action="{{ route('notes.destroy', $note) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this note?')">Delete</button>
                                </form>
                            @elseif($isShared && $permission === 'edit')
                                <a href="{{ route('notes.edit', $note) }}" class="btn btn-warning btn-sm">Edit</a>
                            @endif
                        @else
                            <a href="{{ route('notes.edit', $note) }}" class="btn btn-warning btn-sm">Edit</a>
                            <a href="{{ route('notes.password-protection', $note) }}" class="btn btn-info btn-sm">
                                <i class="fas {{ $note->is_password_protected ? 'fa-lock' : 'fa-unlock' }} me-1"></i>
                                {{ $note->is_password_protected ? 'Change Password' : 'Set Password' }}
                            </a>
                            <form action="{{ route('notes.destroy', $note) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this note?')">Delete</button>
                            </form>
                        @endisset
                    </div>
                </div>

                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success" role="alert">
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="mb-4">
                        {!! nl2br(e($note->content)) !!}
                    </div>

                    <hr>

                    <div class="mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5>Labels</h5>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#manageLabelModal">
                                Manage Labels
                            </button>
                        </div>
                        
                        <div id="labels-container">
                            @if(count($note->labels) > 0)
                                @foreach($note->labels as $label)
                                    <span class="badge mb-1 me-1" style="background-color: {{ $label->color }};">
                                        {{ $label->name }}
                                    </span>
                                @endforeach
                            @else
                                <p>No labels attached to this note.</p>
                            @endif
                        </div>
                    </div>

                    <hr>

                    <div class="mt-4">
                        <h5>Attachments</h5>
                        
                        @if(count($note->attachments) > 0)
                            <ul class="list-group mb-3">
                                @foreach($note->attachments as $attachment)
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-paperclip"></i> {{ $attachment->original_filename }}
                                            <small class="text-muted">({{ round($attachment->file_size / 1024) }} KB)</small>
                                        </div>
                                        <div>
                                            <a href="{{ route('attachments.download', $attachment) }}" class="btn btn-sm btn-primary">Download</a>
                                            <form action="{{ route('notes.attachments.destroy', ['note' => $note, 'attachment' => $attachment]) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this attachment?')">Delete</button>
                                            </form>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p>No attachments yet.</p>
                        @endif

                        <a href="{{ route('notes.attachments.create', $note) }}" class="btn btn-success btn-sm">Attach images to note</a>
                    </div>
                </div>

                <div class="card-footer note-date-footer">
                    <div class="d-flex justify-content-between">
                        <div>
                            Created: {{ $note->created_at->format('M d, Y h:i A') }}
                            @if($note->created_at != $note->updated_at)
                                | Updated: {{ $note->updated_at->format('M d, Y h:i A') }}
                            @endif
                        </div>
                        @isset($isShared)
                            @if($isShared)
                                <div>
                                    <i class="fas fa-user me-1"></i> Shared by: {{ $note->user->name }}
                                </div>
                            @endif
                        @endisset
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <a href="{{ route('notes.index') }}" class="btn btn-secondary">Back to Notes</a>
            </div>
        </div>
    </div>
</div>

<!-- Label Management Modal -->
<div class="modal fade" id="manageLabelModal" tabindex="-1" aria-labelledby="manageLabelModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manageLabelModalLabel">Manage Labels</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="labels-loading" class="text-center mb-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Loading labels...</p>
                </div>
                
                <div id="labels-error" class="alert alert-danger d-none" role="alert">
                    Error loading labels. Please try again.
                </div>
                
                <div id="labels-list" class="d-none">
                    <!-- Labels will be loaded here -->
                </div>
                
                <hr class="my-3">
                
                <div class="d-flex justify-content-between align-items-center">
                    <h6>Add New Label</h6>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#newLabelForm" aria-expanded="false" aria-controls="newLabelForm">
                        <i class="fas fa-plus"></i> Create Label
                    </button>
                </div>
                
                <div class="collapse mt-3" id="newLabelForm">
                    <form id="create-label-form">
                        <div class="mb-3">
                            <label for="new-label-name" class="form-label">Label Name</label>
                            <input type="text" class="form-control" id="new-label-name" required>
                            <div class="invalid-feedback" id="new-label-name-error"></div>
                        </div>
                        <div class="mb-3">
                            <label for="new-label-color" class="form-label">Color</label>
                            <input type="color" class="form-control form-control-color" id="new-label-color" value="#6c757d">
                        </div>
                        <button type="submit" class="btn btn-primary" id="create-label-btn">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true" id="create-label-spinner"></span>
                            Create Label
                        </button>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Constants for note data
        const noteId = {{ $note->id }};
        
        // Elements for label management
        const manageLabelModal = document.getElementById('manageLabelModal');
        const labelsLoading = document.getElementById('labels-loading');
        const labelsError = document.getElementById('labels-error');
        const labelsList = document.getElementById('labels-list');
        const createLabelForm = document.getElementById('create-label-form');
        const newLabelName = document.getElementById('new-label-name');
        const newLabelColor = document.getElementById('new-label-color');
        const createLabelBtn = document.getElementById('create-label-btn');
        const createLabelSpinner = document.getElementById('create-label-spinner');
        
        // Load labels when the modal is shown
        manageLabelModal.addEventListener('show.bs.modal', function () {
            loadLabels();
        });
        
        // Function to load all labels
        function loadLabels() {
            labelsLoading.classList.remove('d-none');
            labelsError.classList.add('d-none');
            labelsList.classList.add('d-none');
            
            // Add timestamp to avoid caching
            const timestamp = new Date().getTime();
            const url = '{{ route('labels.get-all') }}?_=' + timestamp;
            
            fetch(url, {
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache',
                    'Expires': '0'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    console.log('Labels loaded:', data.labels);
                    
                    // Additional validation - Ensure labels belong to the current user
                    const currentUserId = {{ auth()->id() }};
                    
                    // Check if any labels have a different user_id
                    const invalidLabels = data.labels.filter(label => label.user_id !== currentUserId);
                    if (invalidLabels.length > 0) {
                        console.error('Security issue: Found labels from other users', invalidLabels);
                        showError('Security issue detected. Please contact support.');
                        return;
                    }
                    
                    // Always show the labels list, even if empty
                    renderLabels(data.labels);
                    
                    // Show message if provided
                    if (data.message) {
                        labelsList.innerHTML = `<p class="text-info">${data.message}</p>`;
                        labelsList.classList.remove('d-none');
                        labelsLoading.classList.add('d-none');
                    }
                } else {
                    showError(data.message || 'Error loading labels');
                }
            })
            .catch(error => {
                console.error('Error fetching labels:', error);
                showError('Failed to load labels. Please try again.');
            })
            .finally(() => {
                labelsLoading.classList.add('d-none');
            });
        }
        
        // Function to show error message
        function showError(message) {
            labelsError.textContent = message;
            labelsError.classList.remove('d-none');
        }
        
        // Function to render labels list
        function renderLabels(labels) {
            if (!labels || labels.length === 0) {
                // This will be handled by the message in loadLabels
                return;
            }
            
            const currentNoteLabels = @json($note->labels->pluck('id'));
            
            // Sort labels by whether they are attached to the current note
            labels.sort((a, b) => {
                const aIsAttached = currentNoteLabels.includes(a.id);
                const bIsAttached = currentNoteLabels.includes(b.id);
                
                if (aIsAttached && !bIsAttached) return -1;
                if (!aIsAttached && bIsAttached) return 1;
                return a.name.localeCompare(b.name);
            });
            
            const labelsHtml = labels.map(label => {
                const isAttached = currentNoteLabels.includes(label.id);
                
                return `
                    <div class="form-check d-flex justify-content-between align-items-center mb-2 p-2 ${isAttached ? 'bg-light rounded' : ''}">
                        <div>
                            <input class="form-check-input label-checkbox" type="checkbox" value="${label.id}" 
                                id="label-${label.id}" ${isAttached ? 'checked' : ''} 
                                data-label-id="${label.id}">
                            <label class="form-check-label" for="label-${label.id}">
                                <span class="d-inline-block me-2" style="width: 1rem; height: 1rem; background-color: ${label.color}; border-radius: 50%; border: 1px solid #ccc;"></span>
                                ${label.name}
                            </label>
                        </div>
                    </div>
                `;
            }).join('');
            
            labelsList.innerHTML = labelsHtml;
            
            // Attach event listeners to checkboxes
            const checkboxes = labelsList.querySelectorAll('.label-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const labelId = this.getAttribute('data-label-id');
                    const isChecked = this.checked;
                    
                    if (isChecked) {
                        addLabelToNote(labelId, this);
                    } else {
                        removeLabelFromNote(labelId, this);
                    }
                });
            });
            
            labelsList.classList.remove('d-none');
        }
        
        // Function to add a label to the note
        function addLabelToNote(labelId, checkbox) {
            checkbox.disabled = true;
            const formData = new FormData();
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('note_id', noteId);
            formData.append('label_id', labelId);
            
            fetch('{{ route('labels.add-to-note') }}', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateNoteLabelsDisplay();
                } else {
                    showToast('Error', data.message || 'Failed to add label', 'danger');
                    checkbox.checked = false;
                }
            })
            .catch(error => {
                console.error('Error adding label:', error);
                showToast('Error', 'Failed to add label', 'danger');
                checkbox.checked = false;
            })
            .finally(() => {
                checkbox.disabled = false;
            });
        }
        
        // Function to remove a label from the note
        function removeLabelFromNote(labelId, checkbox) {
            checkbox.disabled = true;
            const formData = new FormData();
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('note_id', noteId);
            formData.append('label_id', labelId);
            
            fetch('{{ route('labels.remove-from-note') }}', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateNoteLabelsDisplay();
                } else {
                    showToast('Error', data.message || 'Failed to remove label', 'danger');
                    checkbox.checked = true;
                }
            })
            .catch(error => {
                console.error('Error removing label:', error);
                showToast('Error', 'Failed to remove label', 'danger');
                checkbox.checked = true;
            })
            .finally(() => {
                checkbox.disabled = false;
            });
        }
        
        // Create new label form submission
        if (createLabelForm) {
            createLabelForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const name = newLabelName.value.trim();
                const color = newLabelColor.value;
                
                if (!name) {
                    document.getElementById('new-label-name-error').textContent = 'Label name is required';
                    newLabelName.classList.add('is-invalid');
                    return;
                }
                
                // Clear validation errors
                newLabelName.classList.remove('is-invalid');
                
                // Show loading state
                createLabelBtn.disabled = true;
                createLabelSpinner.classList.remove('d-none');
                
                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('name', name);
                formData.append('color', color);
                
                fetch('{{ route('labels.store') }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reset form
                        newLabelName.value = '';
                        newLabelColor.value = '#6c757d';
                        
                        // Close the collapse
                        const bsCollapse = bootstrap.Collapse.getInstance('#newLabelForm');
                        if (bsCollapse) {
                            bsCollapse.hide();
                        }
                        
                        // Show success message
                        showToast('Success', 'Label created successfully', 'success');
                        
                        // Reload labels
                        loadLabels();
                    } else {
                        if (data.errors && data.errors.name) {
                            document.getElementById('new-label-name-error').textContent = data.errors.name[0];
                            newLabelName.classList.add('is-invalid');
                        } else {
                            showToast('Error', data.message || 'Failed to create label', 'danger');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error creating label:', error);
                    showToast('Error', 'Failed to create label', 'danger');
                })
                .finally(() => {
                    createLabelBtn.disabled = false;
                    createLabelSpinner.classList.add('d-none');
                });
            });
        }
        
        // Function to update the note labels display without reloading the page
        function updateNoteLabelsDisplay() {
            fetch(`/notes/${noteId}?_labels=1`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const labelsContainer = document.getElementById('labels-container');
                        
                        if (data.labels.length > 0) {
                            const labelsHtml = data.labels.map(label => 
                                `<span class="badge mb-1 me-1" style="background-color: ${label.color};">${label.name}</span>`
                            ).join('');
                            
                            labelsContainer.innerHTML = labelsHtml;
                        } else {
                            labelsContainer.innerHTML = '<p>No labels attached to this note.</p>';
                        }
                    } else {
                        showToast('Error', data.message || 'Failed to update labels display', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error updating labels display:', error);
                });
        }
        
        // Function to show a toast notification
        function showToast(title, message, type = 'info') {
            // Check if the toast container exists, if not create it
            let toastContainer = document.getElementById('toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toast-container';
                toastContainer.className = 'position-fixed bottom-0 end-0 p-3';
                document.body.appendChild(toastContainer);
            }
            
            // Create toast element
            const toastId = 'toast-' + Date.now();
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0`;
            toast.id = toastId;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            
            // Create toast content
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <strong>${title}:</strong> ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;
            
            // Add toast to container
            toastContainer.appendChild(toast);
            
            // Initialize and show toast
            const bsToast = new bootstrap.Toast(toast, {
                animation: true,
                autohide: true,
                delay: 5000
            });
            bsToast.show();
            
            // Remove toast after it's hidden
            toast.addEventListener('hidden.bs.toast', function () {
                toast.remove();
            });
        }
    });
</script>
@endpush
@endsection 