@php
use Illuminate\Support\Str;
@endphp

@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ __('My Notes') }}</span>
                    <div>
                        <div class="btn-group me-2" role="group" aria-label="View options">
                            <button type="button" class="btn btn-outline-secondary" id="grid-view-btn">
                                <i class="fas fa-th"></i> Grid
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="list-view-btn">
                                <i class="fas fa-list"></i> List
                            </button>
                        </div>
                        <a href="{{ route('labels.index') }}" class="btn btn-info btn-sm me-2">Manage Labels</a>
                        <a href="{{ route('notes.create') }}" class="btn btn-primary btn-sm">Create New Note</a>
                    </div>
                </div>

                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success" role="alert">
                            {{ session('success') }}
                        </div>
                    @endif

                    <!-- Live Search Box -->
                    <div class="d-flex mb-4">
                        <!-- Label Filter -->
                        @if(isset($labels) && $labels->count() > 0)
                            <div class="dropdown me-2">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="labelFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    @if(isset($selectedLabel))
                                        {{ $labels->firstWhere('id', $selectedLabel)->name ?? 'Filter by Label' }}
                                    @else
                                        Filter by Label
                                    @endif
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="labelFilterDropdown">
                                    <li><a class="dropdown-item {{ !isset($selectedLabel) ? 'active' : '' }}" href="{{ route('notes.index') }}">All Notes</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    @foreach($labels as $label)
                                        <li>
                                            <a class="dropdown-item {{ isset($selectedLabel) && $selectedLabel == $label->id ? 'active' : '' }}" 
                                               href="{{ route('notes.index', ['label_id' => $label->id]) }}">
                                                <span class="d-inline-block me-2" style="width: 0.75rem; height: 0.75rem; background-color: {{ $label->color }}; border-radius: 50%; border: 1px solid #ccc;"></span>
                                                {{ $label->name }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        
                        <div class="input-group flex-grow-1">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="search-notes" class="form-control" placeholder="Search notes by title or content..." aria-label="Search notes">
                            <button id="clear-search" class="btn btn-outline-secondary d-none" type="button">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div id="search-feedback" class="form-text d-none mb-3">
                        <span id="search-count">0</span> results found <span id="search-spinner" class="spinner-border spinner-border-sm ms-2" role="status"></span>
                    </div>

                    @if (count($notes) > 0)
                        <div id="grid-view" class="row">
                            @foreach ($notes as $note)
                                <div class="col-md-4 mb-4">
                                    <div class="card h-100 note-card" style="background-color: {{ $note->color ?? '#ffffff' }}; color: {{ in_array($note->color, ['#212529', '#343a40', '#495057', '#000000', '#111111', '#222222', '#333333']) ? '#ffffff' : '#000000' }};">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h5 class="card-title mb-0">{{ $note->title }}</h5>
                                                <div class="d-flex align-items-center">
                                                    @if($note->is_password_protected)
                                                    <span class="me-2 text-warning" title="Password Protected">
                                                        <i class="fas fa-lock"></i>
                                                    </span>
                                                    @endif
                                                    @if($note->shares->count() > 0)
                                                    <span class="me-2 text-primary" title="Shared with {{ $note->shares->count() }} user(s)">
                                                        <i class="fas fa-share-alt"></i>
                                                        <small>{{ $note->shares->count() }}</small>
                                                    </span>
                                                    @endif
                                                    <form action="{{ route('notes.toggle-pin', $note) }}" method="POST" class="pin-form">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="btn {{ $note->pinned ? 'btn-warning' : 'btn-outline-secondary' }} btn-sm">
                                                            <i class="fas fa-thumbtack"></i> {{ $note->pinned ? 'Unpin' : 'Pin' }}
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                            <p class="card-text">{{ Str::limit($note->content, 100) }}</p>
                                            <div class="mt-auto">
                                                <a href="{{ route('notes.show', $note) }}" class="btn btn-info btn-sm">View</a>
                                                <a href="{{ route('notes.edit', $note) }}" class="btn btn-warning btn-sm">Edit</a>
                                                <button type="button" class="btn btn-success btn-sm attach-labels-btn" data-note-id="{{ $note->id }}" data-note-title="{{ $note->title }}" data-bs-toggle="modal" data-bs-target="#attachLabelsModal">
                                                    <i class="fas fa-tags"></i> Labels
                                                </button>
                                                <form action="{{ route('notes.destroy', $note) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this note?')">Delete</button>
                                                </form>
                                            </div>
                                            @if($note->labels && $note->labels->count() > 0)
                                                <div class="mt-2">
                                                    @foreach($note->labels as $label)
                                                        <span class="badge" style="background-color: {{ $label->color }};">
                                                            {{ $label->name }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        @if($note->pinned)
                                        <div class="card-footer text-muted py-1 text-center">
                                            <small><i class="fas fa-thumbtack me-1"></i> Pinned</small>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        <div id="list-view" class="d-none">
                            <div class="list-group">
                                @foreach ($notes as $note)
                                    <div class="list-group-item list-group-item-action note-card d-flex justify-content-between align-items-center" 
                                         data-note-color="{{ $note->color ?? '#ffffff' }}"
                                         style="background-color: {{ $note->color ?? '#ffffff' }} !important; color: {{ in_array($note->color, ['#212529', '#343a40', '#495057', '#000000', '#111111', '#222222', '#333333']) ? '#ffffff' : '#000000' }} !important; border-color: rgba(0,0,0,.125);">
                                        <div>
                                            <div class="d-flex align-items-center">
                                                <h5 class="mb-1">{{ $note->title }}</h5>
                                                @if($note->is_password_protected)
                                                <span class="ms-2 text-warning" title="Password Protected">
                                                    <i class="fas fa-lock"></i>
                                                </span>
                                                @endif
                                                @if($note->shares->count() > 0)
                                                <span class="ms-2 text-primary" title="Shared with {{ $note->shares->count() }} user(s)">
                                                    <i class="fas fa-share-alt"></i>
                                                    <small>{{ $note->shares->count() }}</small>
                                                </span>
                                                @endif
                                                @if($note->pinned)
                                                <span class="badge bg-warning text-dark ms-2">
                                                    <i class="fas fa-thumbtack"></i> Pinned
                                                </span>
                                                @endif
                                            </div>
                                            <p class="mb-1">{{ Str::limit($note->content, 150) }}</p>
                                            @if($note->labels && $note->labels->count() > 0)
                                                <div class="mt-1">
                                                    @foreach($note->labels as $label)
                                                        <span class="badge" style="background-color: {{ $label->color }};">
                                                            {{ $label->name }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <div>
                                            <form action="{{ route('notes.toggle-pin', $note) }}" method="POST" class="d-inline pin-form">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn {{ $note->pinned ? 'btn-warning' : 'btn-outline-secondary' }} btn-sm">
                                                    <i class="fas fa-thumbtack"></i> {{ $note->pinned ? 'Unpin' : 'Pin' }}
                                                </button>
                                            </form>
                                            <a href="{{ route('notes.show', $note) }}" class="btn btn-info btn-sm">View</a>
                                            <a href="{{ route('notes.edit', $note) }}" class="btn btn-warning btn-sm">Edit</a>
                                            <button type="button" class="btn btn-success btn-sm attach-labels-btn" data-note-id="{{ $note->id }}" data-note-title="{{ $note->title }}" data-bs-toggle="modal" data-bs-target="#attachLabelsModal">
                                                <i class="fas fa-tags"></i> Labels
                                            </button>
                                            <form action="{{ route('notes.destroy', $note) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this note?')">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <p>You don't have any notes yet. <a href="{{ route('notes.create') }}">Create your first note!</a></p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Always make sure these elements exist, even after page reload
        const gridViewBtn = document.getElementById('grid-view-btn');
        const listViewBtn = document.getElementById('list-view-btn');
        const gridView = document.getElementById('grid-view');
        const listView = document.getElementById('list-view');
        
        // Search elements
        const searchInput = document.getElementById('search-notes');
        const clearSearchBtn = document.getElementById('clear-search');
        const searchFeedback = document.getElementById('search-feedback');
        const searchCount = document.getElementById('search-count');
        let searchTimeout = null;
        
        // Make sure all elements exist before continuing
        if (!gridViewBtn || !listViewBtn || !gridView || !listView) {
            console.error('Missing elements for view switching');
            return;
        }
        
        // Toggle between grid and list view
        gridViewBtn.addEventListener('click', function() {
            console.log('Grid view clicked');
            gridView.classList.remove('d-none');
            listView.classList.add('d-none');
            gridViewBtn.classList.add('active');
            listViewBtn.classList.remove('active');
            localStorage.setItem('notesViewPreference', 'grid');
        });
        
        listViewBtn.addEventListener('click', function() {
            console.log('List view clicked');
            gridView.classList.add('d-none');
            listView.classList.remove('d-none');
            gridViewBtn.classList.remove('active');
            listViewBtn.classList.add('active');
            localStorage.setItem('notesViewPreference', 'list');
        });
        
        // Check if user has a saved preference
        const savedPreference = localStorage.getItem('notesViewPreference');
        console.log('Saved preference:', savedPreference);
        
        // Apply the saved preference or default to grid view
        if (savedPreference === 'list') {
            listViewBtn.click();
        } else {
            // Default to grid view if no preference or preference is grid
            gridViewBtn.click();
        }
        
        // Handle Attach Labels modal
        const attachLabelsModal = document.getElementById('attachLabelsModal');
        const attachLabelsButtons = document.querySelectorAll('.attach-labels-btn');
        let currentNoteId = null;
        
        // Elements for label management
        const labelsLoading = document.getElementById('labels-loading');
        const labelsError = document.getElementById('labels-error');
        const labelsList = document.getElementById('labels-list');
        const createLabelForm = document.getElementById('create-label-form');
        const newLabelName = document.getElementById('new-label-name');
        const newLabelColor = document.getElementById('new-label-color');
        const createLabelBtn = document.getElementById('create-label-btn');
        const createLabelSpinner = document.getElementById('create-label-spinner');
        
        // Attach event listeners to buttons
        attachLabelsButtons.forEach(button => {
            button.addEventListener('click', function() {
                currentNoteId = this.getAttribute('data-note-id');
                const noteTitle = this.getAttribute('data-note-title');
                
                // Update the modal title with note title
                document.getElementById('note-title-display').textContent = noteTitle;
                
                // Clear any existing checkboxes
                if (labelsList) {
                    labelsList.innerHTML = '';
                }
                
                // Load labels
                loadLabels();
            });
        });
        
        // Function to load all labels
        function loadLabels() {
            if (!labelsLoading || !labelsError || !labelsList) return;
            
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
            if (!labelsError) return;
            labelsError.textContent = message;
            labelsError.classList.remove('d-none');
        }
        
        // Function to render labels list
        function renderLabels(labels) {
            if (!labelsList) return;
            
            if (!labels || labels.length === 0) {
                // This will be handled by the message in loadLabels
                return;
            }
            
            // Show loading indicator
            labelsLoading.classList.remove('d-none');
            labelsList.classList.add('d-none');
            
            // Fetch the current note's labels
            fetch(`/notes/${currentNoteId}?_labels=1`)
                .then(response => response.json())
                .then(data => {
                    // Hide loading indicator
                    labelsLoading.classList.add('d-none');
                    
                    if (data.success) {
                        // Extract IDs as numbers to ensure consistent comparison
                        const currentNoteLabels = data.labels.map(label => Number(label.id));
                        console.log('Note labels:', currentNoteLabels); // Debug
                        
                        // Sort labels by whether they are attached to the current note
                        labels.sort((a, b) => {
                            const aIsAttached = currentNoteLabels.includes(Number(a.id));
                            const bIsAttached = currentNoteLabels.includes(Number(b.id));
                            
                            if (aIsAttached && !bIsAttached) return -1;
                            if (!aIsAttached && bIsAttached) return 1;
                            return a.name.localeCompare(b.name);
                        });
                        
                        const labelsHtml = labels.map(label => {
                            const labelId = Number(label.id);
                            const isAttached = currentNoteLabels.includes(labelId);
                            console.log(`Label ${labelId} (${label.name}) attached: ${isAttached}`); // Debug
                            
                            return `
                                <div class="custom-label-check p-2 ${isAttached ? 'bg-light rounded' : ''}">
                                    <span class="form-check-input-container">
                                        <input class="form-check-input" type="checkbox" value="${labelId}" 
                                            id="label-${labelId}" ${isAttached ? 'checked' : ''} 
                                            data-label-id="${labelId}">
                                    </span>
                                    <label class="form-check-label" for="label-${labelId}">
                                        <span class="d-inline-block me-2" style="width: 1rem; height: 1rem; background-color: ${label.color}; border-radius: 50%; border: 1px solid #ccc;"></span>
                                        ${label.name}
                                    </label>
                                </div>
                            `;
                        }).join('');
                        
                        labelsList.innerHTML = labelsHtml;
                        
                        // Attach event listeners to checkboxes
                        const checkboxes = labelsList.querySelectorAll('.form-check-input');
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
                    } else {
                        showError('Error loading note labels');
                    }
                })
                .catch(error => {
                    // Hide loading indicator on error
                    labelsLoading.classList.add('d-none');
                    console.error('Error fetching note labels:', error);
                    showError('Failed to load note labels');
                });
        }
        
        // Function to add a label to the note
        function addLabelToNote(labelId, checkbox) {
            checkbox.disabled = true;
            const formData = new FormData();
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('note_id', currentNoteId);
            formData.append('label_id', labelId);
            
            // Show a loading indicator on the checkbox
            const originalParentBg = checkbox.closest('.custom-label-check').style.backgroundColor;
            checkbox.closest('.custom-label-check').style.backgroundColor = 'rgba(0, 123, 255, 0.1)';
            
            fetch('{{ route('labels.add-to-note') }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    checkbox.closest('.custom-label-check').classList.add('bg-light', 'rounded');
                    // Ensure checkbox is checked
                    checkbox.checked = true;
                    showToast('Success', data.message || 'Label attached successfully', 'success');
                    
                    // Update labels display on the note
                    updateNoteLabelsDisplay(currentNoteId, true);
                    
                    // Refresh labels list to ensure UI stays in sync
                    refreshLabelsList();
                } else {
                    showToast('Error', data.message || 'Failed to attach label', 'danger');
                    checkbox.checked = false;
                    refreshLabelsList(); // Refresh in case of error
                }
            })
            .catch(error => {
                console.error('Error adding label:', error);
                showToast('Error', 'Failed to attach label', 'danger');
                checkbox.checked = false;
                refreshLabelsList(); // Refresh in case of error
            })
            .finally(() => {
                checkbox.disabled = false;
                checkbox.closest('.custom-label-check').style.backgroundColor = originalParentBg;
            });
        }
        
        // Function to remove a label from the note
        function removeLabelFromNote(labelId, checkbox) {
            checkbox.disabled = true;
            const formData = new FormData();
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('note_id', currentNoteId);
            formData.append('label_id', labelId);
            
            // Show a loading indicator on the checkbox
            const originalParentBg = checkbox.closest('.custom-label-check').style.backgroundColor;
            checkbox.closest('.custom-label-check').style.backgroundColor = 'rgba(220, 53, 69, 0.1)';
            
            fetch('{{ route('labels.remove-from-note') }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    checkbox.closest('.custom-label-check').classList.remove('bg-light', 'rounded');
                    // Ensure checkbox is unchecked
                    checkbox.checked = false;
                    showToast('Success', data.message || 'Label removed successfully', 'success');
                    
                    // Update labels display on the note
                    updateNoteLabelsDisplay(currentNoteId, false);
                    
                    // Refresh labels list to ensure UI stays in sync
                    refreshLabelsList();
                } else {
                    showToast('Error', data.message || 'Failed to remove label', 'danger');
                    checkbox.checked = true;
                    refreshLabelsList(); // Refresh in case of error
                }
            })
            .catch(error => {
                console.error('Error removing label:', error);
                showToast('Error', 'Failed to remove label', 'danger');
                checkbox.checked = true;
                refreshLabelsList(); // Refresh in case of error
            })
            .finally(() => {
                checkbox.disabled = false;
                checkbox.closest('.custom-label-check').style.backgroundColor = originalParentBg;
            });
        }
        
        // Function to refresh labels list to ensure UI is in sync with server state
        function refreshLabelsList() {
            // Wait a bit before refreshing to ensure server has updated
            setTimeout(() => {
                if (labelsList) {
                    labelsList.innerHTML = '';
                }
                loadLabels();
            }, 300);
        }
        
        // Function to update the note labels display in both grid and list views
        function updateNoteLabelsDisplay(noteId, wait=true) {
            // Add a small delay to make sure UI is updated properly
            const delay = wait ? 500 : 0;
            
            setTimeout(() => {
                fetch(`/notes/${noteId}?_labels=1`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Find the note in both grid and list views
                            const gridNote = document.querySelector(`.col-md-4 [data-note-id="${noteId}"]`);
                            const listNote = document.querySelector(`.list-group-item [data-note-id="${noteId}"]`);
                            
                            if (gridNote || listNote) {
                                const labelsHtml = data.labels.map(label => 
                                    `<span class="badge mb-1 me-1" style="background-color: ${label.color};">${label.name}</span>`
                                ).join('');
                                
                                // Update grid view
                                if (gridNote) {
                                    const gridNoteCard = gridNote.closest('.col-md-4');
                                    let labelsContainer = gridNoteCard.querySelector('.note-labels');
                                    
                                    if (!labelsContainer) {
                                        labelsContainer = document.createElement('div');
                                        labelsContainer.className = 'mt-2 note-labels';
                                        const cardBody = gridNoteCard.querySelector('.card-body');
                                        
                                        // Insert before any possible footer
                                        const footer = gridNoteCard.querySelector('.card-footer');
                                        if (footer) {
                                            cardBody.insertBefore(labelsContainer, footer);
                                        } else {
                                            cardBody.appendChild(labelsContainer);
                                        }
                                    }
                                    
                                    if (data.labels.length > 0) {
                                        labelsContainer.innerHTML = labelsHtml;
                                        labelsContainer.classList.remove('d-none');
                                    } else {
                                        labelsContainer.classList.add('d-none');
                                    }
                                }
                                
                                // Update list view
                                if (listNote) {
                                    const listNoteItem = listNote.closest('.list-group-item');
                                    let labelsContainer = listNoteItem.querySelector('.note-labels');
                                    
                                    if (!labelsContainer) {
                                        labelsContainer = document.createElement('div');
                                        labelsContainer.className = 'mt-1 note-labels';
                                        const contentDiv = listNoteItem.querySelector('div:first-child');
                                        contentDiv.appendChild(labelsContainer);
                                    }
                                    
                                    if (data.labels.length > 0) {
                                        labelsContainer.innerHTML = labelsHtml;
                                        labelsContainer.classList.remove('d-none');
                                    } else {
                                        labelsContainer.classList.add('d-none');
                                    }
                                }
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error updating labels display:', error);
                    });
            }, delay);
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
        
        // Live search functionality
        if (searchInput) {
            // Debounce function to prevent too many requests
            function debounce(func, delay) {
                return function() {
                    const context = this;
                    const args = arguments;
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => func.apply(context, args), delay);
                };
            }
            
            // Function to filter notes
            const filterNotes = debounce(function(searchTerm) {
                searchTerm = searchTerm.toLowerCase().trim();
                
                // Show search spinner
                const searchSpinner = document.getElementById('search-spinner');
                if (searchSpinner) {
                    searchSpinner.classList.remove('d-none');
                }
                
                // Show/hide clear button based on search term
                if (searchTerm.length > 0) {
                    clearSearchBtn.classList.remove('d-none');
                    searchFeedback.classList.remove('d-none');
                } else {
                    clearSearchBtn.classList.add('d-none');
                    searchFeedback.classList.add('d-none');
                }
                
                // Get all notes in both views
                const gridNotes = gridView.querySelectorAll('.col-md-4');
                const listNotes = listView.querySelectorAll('.list-group-item');
                
                let matchCount = 0;
                
                // Function to highlight matches
                function highlightText(element, term) {
                    if (!term || term === '') {
                        // Restore original text if no search term
                        if (element.dataset.originalText) {
                            element.innerHTML = element.dataset.originalText;
                            delete element.dataset.originalText;
                        }
                        return;
                    }
                    
                    // Store original text if not already stored
                    if (!element.dataset.originalText) {
                        element.dataset.originalText = element.innerHTML;
                    }
                    
                    const originalText = element.dataset.originalText;
                    const regex = new RegExp(`(${term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
                    element.innerHTML = originalText.replace(regex, '<span class="highlight">$1</span>');
                }
                
                // Filter grid notes
                gridNotes.forEach(note => {
                    const titleEl = note.querySelector('.card-title');
                    const contentEl = note.querySelector('.card-text');
                    
                    if (!titleEl || !contentEl) return;
                    
                    const title = titleEl.dataset.originalText 
                        ? titleEl.dataset.originalText.toLowerCase() 
                        : titleEl.textContent.toLowerCase();
                    const content = contentEl.dataset.originalText 
                        ? contentEl.dataset.originalText.toLowerCase() 
                        : contentEl.textContent.toLowerCase();
                    
                    if (searchTerm === '' || title.includes(searchTerm) || content.includes(searchTerm)) {
                        note.classList.remove('d-none');
                        highlightText(titleEl, searchTerm);
                        highlightText(contentEl, searchTerm);
                        matchCount++;
                    } else {
                        note.classList.add('d-none');
                        // Restore original text
                        highlightText(titleEl, '');
                        highlightText(contentEl, '');
                    }
                });
                
                // Filter list notes
                listNotes.forEach(note => {
                    const titleEl = note.querySelector('h5.mb-1');
                    const contentEl = note.querySelector('p.mb-1');
                    
                    if (!titleEl || !contentEl) return;
                    
                    const title = titleEl.dataset.originalText 
                        ? titleEl.dataset.originalText.toLowerCase() 
                        : titleEl.textContent.toLowerCase();
                    const content = contentEl.dataset.originalText 
                        ? contentEl.dataset.originalText.toLowerCase() 
                        : contentEl.textContent.toLowerCase();
                    
                    if (searchTerm === '' || title.includes(searchTerm) || content.includes(searchTerm)) {
                        note.classList.remove('d-none');
                        highlightText(titleEl, searchTerm);
                        highlightText(contentEl, searchTerm);
                    } else {
                        note.classList.add('d-none');
                        // Restore original text
                        highlightText(titleEl, '');
                        highlightText(contentEl, '');
                    }
                });
                
                // Update counter
                searchCount.textContent = matchCount;
                
                // Hide spinner after search completes
                if (searchSpinner) {
                    setTimeout(() => {
                        searchSpinner.classList.add('d-none');
                    }, 200);
                }
                
                // Show "no results" message if needed
                const gridNoResults = document.getElementById('grid-no-results');
                const listNoResults = document.getElementById('list-no-results');
                
                if (matchCount === 0 && searchTerm !== '') {
                    // Create "no results" messages if they don't exist
                    if (!gridNoResults) {
                        const noResultsDiv = document.createElement('div');
                        noResultsDiv.id = 'grid-no-results';
                        noResultsDiv.className = 'col-12 text-center my-4';
                        noResultsDiv.innerHTML = `
                            <div class="alert alert-info">
                                No notes found matching "<strong>${searchTerm}</strong>"
                            </div>
                        `;
                        gridView.appendChild(noResultsDiv);
                    } else {
                        gridNoResults.querySelector('strong').textContent = searchTerm;
                        gridNoResults.classList.remove('d-none');
                    }
                    
                    if (!listNoResults) {
                        const noResultsDiv = document.createElement('div');
                        noResultsDiv.id = 'list-no-results';
                        noResultsDiv.className = 'text-center my-4';
                        noResultsDiv.innerHTML = `
                            <div class="alert alert-info">
                                No notes found matching "<strong>${searchTerm}</strong>"
                            </div>
                        `;
                        listView.appendChild(noResultsDiv);
                    } else {
                        listNoResults.querySelector('strong').textContent = searchTerm;
                        listNoResults.classList.remove('d-none');
                    }
                } else {
                    // Hide "no results" messages if they exist
                    if (gridNoResults) {
                        gridNoResults.classList.add('d-none');
                    }
                    if (listNoResults) {
                        listNoResults.classList.add('d-none');
                    }
                }
            }, 300); // 300ms debounce
            
            // Event listeners for search
            searchInput.addEventListener('input', function() {
                filterNotes(this.value);
            });
            
            // Clear search
            if (clearSearchBtn) {
                clearSearchBtn.addEventListener('click', function() {
                    searchInput.value = '';
                    filterNotes('');
                    searchInput.focus();
                });
            }
        }
        
        // Auto-detect dark note colors and apply appropriate class for contrast
        const noteCards = document.querySelectorAll('.note-card');
        noteCards.forEach(card => {
            const backgroundColor = getComputedStyle(card).backgroundColor;
            const rgb = backgroundColor.match(/\d+/g);
            
            if (rgb && rgb.length >= 3) {
                const r = parseInt(rgb[0]);
                const g = parseInt(rgb[1]);
                const b = parseInt(rgb[2]);
                
                // Calculate brightness (higher values are lighter colors)
                const brightness = (r * 299 + g * 587 + b * 114) / 1000;
                
                // If the note has a dark background color
                if (brightness < 128) {
                    card.classList.add('dark-note');
                }
            }
        });
        
        // AJAX for pin/unpin functionality
        const pinForms = document.querySelectorAll('.pin-form');
        pinForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(form);
                const actionUrl = form.getAttribute('action');
                const submitButton = form.querySelector('button[type="submit"]');
                const noteCard = form.closest('.note-card');
                const gridItem = form.closest('.col-md-4');
                
                // Store original button text
                const originalButtonHtml = submitButton.innerHTML;
                
                // Update button to show loading
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...';
                submitButton.disabled = true;
                
                fetch(actionUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update button appearance based on pin status
                        if (data.pinned) {
                            submitButton.classList.remove('btn-outline-secondary');
                            submitButton.classList.add('btn-warning');
                            submitButton.innerHTML = '<i class="fas fa-thumbtack"></i> Unpin';
                            
                            // Add pinned indication to the card
                            if (gridItem) {
                                // For grid view
                                if (!noteCard.querySelector('.card-footer')) {
                                    const footer = document.createElement('div');
                                    footer.className = 'card-footer text-muted py-1 text-center';
                                    footer.innerHTML = '<small><i class="fas fa-thumbtack me-1"></i> Pinned</small>';
                                    noteCard.appendChild(footer);
                                }
                            } else {
                                // For list view
                                const titleContainer = noteCard.querySelector('.d-flex.align-items-center');
                                if (titleContainer && !titleContainer.querySelector('.badge')) {
                                    const badge = document.createElement('span');
                                    badge.className = 'badge bg-warning text-dark ms-2';
                                    badge.innerHTML = '<i class="fas fa-thumbtack"></i> Pinned';
                                    titleContainer.appendChild(badge);
                                }
                            }
                            
                            // Show success message
                            showToast('Success', 'Note pinned successfully!', 'success');
                            
                            // Move the note to the top - requires page reload to actually resort
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                            
                        } else {
                            submitButton.classList.remove('btn-warning');
                            submitButton.classList.add('btn-outline-secondary');
                            submitButton.innerHTML = '<i class="fas fa-thumbtack"></i> Pin';
                            
                            // Remove pinned indication
                            if (gridItem) {
                                // For grid view
                                const footer = noteCard.querySelector('.card-footer');
                                if (footer) {
                                    footer.remove();
                                }
                            } else {
                                // For list view
                                const badge = noteCard.querySelector('.badge');
                                if (badge) {
                                    badge.remove();
                                }
                            }
                            
                            // Show success message
                            showToast('Success', 'Note unpinned successfully!', 'success');
                            
                            // Give visual feedback before reloading
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        }
                    } else {
                        // Show error message
                        showToast('Error', data.message || 'Error updating pin status', 'danger');
                        submitButton.innerHTML = originalButtonHtml;
                        submitButton.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error', 'An error occurred while updating pin status', 'danger');
                    submitButton.innerHTML = originalButtonHtml;
                    submitButton.disabled = false;
                });
            });
        });
    });
</script>
@endpush

@push('styles')
<style>
    /* Highlight matching search terms */
    .highlight {
        background-color: #fff3cd;
        padding: 0 2px;
        border-radius: 2px;
    }
    
    /* Smooth transitions for search filtering */
    #grid-view .col-md-4 {
        transition: opacity 0.3s ease;
    }
    
    #list-view .list-group-item {
        transition: opacity 0.3s ease;
    }
    
    /* Add a fade effect to the search feedback */
    #search-feedback {
        opacity: 0;
        transition: opacity 0.2s ease;
    }
    
    #search-feedback.d-block, #search-feedback:not(.d-none) {
        opacity: 1;
    }
    
    /* Make the search bar more prominent */
    #search-notes:focus {
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        border-color: #86b7fe;
    }
    
    /* Style for the clear button */
    #clear-search {
        transition: opacity 0.2s ease;
    }
    
    #clear-search:hover {
        background-color: #f8f9fa;
    }
    
    /* Custom styles for label checkboxes - make sure they override Bootstrap defaults */
    #attachLabelsModal .custom-label-check {
        position: relative !important;
        padding-left: 0 !important;
        margin-bottom: 0.5rem !important;
    }
    
    #attachLabelsModal .custom-label-check .form-check-input {
        position: static !important;
        margin-top: 0 !important;
        margin-left: 0 !important;
        float: none !important;
    }
    
    #attachLabelsModal .form-check-input-container {
        display: inline-flex !important;
        justify-content: center !important;
        align-items: center !important;
        width: 1.5rem !important;
        vertical-align: middle !important;
    }
    
    #attachLabelsModal .form-check-label {
        display: inline-flex !important;
        align-items: center !important;
        margin-left: 0.5rem !important;
        vertical-align: middle !important;
    }
</style>
@endpush

<!-- Attach Labels Modal -->
<div class="modal fade" id="attachLabelsModal" tabindex="-1" aria-labelledby="attachLabelsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="attachLabelsModalLabel">Attach Labels to Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Attach labels to "<span id="note-title-display"></span>"</p>
                
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
                    <h6>Create New Label</h6>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#newLabelForm" aria-expanded="false" aria-controls="newLabelForm">
                        <i class="fas fa-plus"></i> New Label
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

@endsection 