@php
use Illuminate\Support\Facades\Auth;
@endphp
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ __('Edit Note') }}</span>
                    <div class="d-flex align-items-center">
                        <div id="collaborators-list" class="me-2">
                            <!-- Collaborators will appear here -->
                        </div>
                        <a href="{{ route('notes.show', $note) }}" class="btn btn-secondary btn-sm">Back to Note</a>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Real-time collaboration notification -->
                    <div id="collaborationAlert" class="alert alert-info mb-3 {{ $note->sharedWith->count() > 0 ? '' : 'd-none' }}">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-users me-2"></i>
                            <div>
                                <strong>Collaborative Editing</strong>
                                <p class="mb-0">This note is being edited in real-time. Any changes you make will be visible to other collaborators immediately.</p>
                            </div>
                        </div>
                    </div>
                    
                    <form id="noteForm" action="{{ route('notes.update', $note) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $note->title) }}" required autocomplete="off">
                            @error('title')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="content" class="form-label">Content</label>
                            <textarea class="form-control @error('content') is-invalid @enderror" id="content" name="content" rows="8" required>{{ old('content', $note->content) }}</textarea>
                            @error('content')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        
                        <!-- Note Color field removed as per requirements -->
                        <input type="hidden" id="color" name="color" value="{{ $note->color ?? (Auth::user()->preferences['note_color'] ?? '#ffffff') }}">

                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary save-button">Save Note</button>
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
<style>
    @keyframes highlight-pulse {
        0% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(0, 123, 255, 0); }
        100% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0); }
    }
    
    .highlight-pulse {
        animation: highlight-pulse 2s infinite;
        border-radius: 5px;
    }
    
    .collaborator-container {
        transition: all 0.3s ease;
    }
    
    #collaborators-list {
        min-height: 30px;
        display: inline-flex;
        align-items: center;
        background: transparent !important;
    }
    
    #collaborator-count {
        display: inline-flex;
        align-items: center;
    }
    
    /* Custom dark mode toast styles */
    body.dark-theme .collaboration-toast {
        background-color: #343a40 !important;
        color: #f8f9fa !important;
        border: 1px solid #495057 !important;
    }
    
    body.dark-theme .collaboration-toast .toast-header {
        background-color: #212529 !important;
        color: #f8f9fa !important;
        border-bottom: 1px solid #495057 !important;
    }
    
    body.dark-theme .collaboration-toast .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
    }
</style>
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Debug Pusher connection
        console.log('Pusher Config:', {
            key: "{{ config('broadcasting.connections.pusher.key') }}",
            cluster: "{{ config('broadcasting.connections.pusher.options.cluster') }}",
            host: "{{ config('broadcasting.connections.pusher.options.host') }}",
            port: "{{ config('broadcasting.connections.pusher.options.port') }}",
        });
        
        // Listen for Pusher connection events
        if (window.Echo) {
            window.Echo.connector.pusher.connection.bind('connected', (data) => {
                console.log('✅ Pusher Connected!', data);
            });
            
            window.Echo.connector.pusher.connection.bind('error', (error) => {
                console.error('❌ Pusher Connection Error:', error);
            });
        } else {
            console.error('❌ Echo is not defined! Broadcasting will not work.');
        }
        
        const form = document.getElementById('noteForm');
        const title = document.getElementById('title');
        const content = document.getElementById('content');
        const saveStatus = document.getElementById('saveStatus');
        const autoSaveSpinner = document.getElementById('autoSaveSpinner');
        const autoSaveText = document.getElementById('autoSaveText');
        const collaboratorsList = document.getElementById('collaborators-list');
        const collaborationAlert = document.getElementById('collaborationAlert');
        
        let typingTimer;
        const doneTypingInterval = 1000; // Save after 1 second of inactivity
        let isDirty = false;
        let currentNoteId = "{{ $note->id }}"; // Store the current note ID
        let lastTitleUpdate = null;
        let lastContentUpdate = null;
        let isProcessingExternalUpdate = false;
        let collaborators = {};
        let autoSaveEnabled = true; // By default, AutoSave is enabled
        let collaborationActive = {{ $note->sharedWith->count() > 0 ? 'true' : 'false' }}; // Whether collaboration is active
        let heartbeatTimer;
        let lastHeartbeat = Date.now();
        let heartbeatInterval = 10000; // 10 seconds
        let userActivityTimeout = 30000; // 30 seconds
        
        function showSaveStatus(message, type = 'info', isAutoSave = false) {
            saveStatus.textContent = message;
            saveStatus.classList.remove('d-none', 'alert-info', 'alert-success', 'alert-danger');
            saveStatus.classList.add(`alert-${type}`);
            
            // Auto-hide success messages after 3 seconds
            if (type === 'success') {
                setTimeout(() => {
                    saveStatus.classList.add('d-none');
                }, 3000);
            }
            
            if (isAutoSave) {
                autoSaveSpinner.classList.remove('d-none');
                if (text) {
                    autoSaveText.textContent = text;
                }
            }
        }
        
        function hideAutoSaveSpinner() {
            setTimeout(() => {
                autoSaveSpinner.classList.add('d-none');
            }, 1000);
        }
        
        function updateCollaborationStatus() {
            const collaboratorCount = Object.keys(collaborators).length;
            // Don't count the current user when determining if there are other collaborators 
            const otherCollaboratorsCount = Object.keys(collaborators).filter(id => id != {{ Auth::id() }}).length;
            console.log(`Updating collaboration status. Total: ${collaboratorCount}, Others: ${otherCollaboratorsCount}`);
            
            // Update UI based on number of collaborators (excluding current user)
            if (otherCollaboratorsCount > 0) {
                console.log('Disabling AutoSave - other collaborators detected');
                // Disable AutoSave when there are other collaborators
                autoSaveEnabled = false;
                
                // Show collaboration message
                collaborationAlert.classList.remove('d-none');
                
                // Add a message about AutoSave being disabled
                const autoSaveMessage = document.createElement('p');
                autoSaveMessage.className = 'mb-0 mt-2';
                autoSaveMessage.innerHTML = '<strong>Note:</strong> AutoSave is disabled in collaborative mode. Please use the Save button to save your changes.';
                
                // Replace any existing message or append new one
                const existingMessage = collaborationAlert.querySelector('.autosave-message');
                if (existingMessage) {
                    existingMessage.remove();
                }
                autoSaveMessage.classList.add('autosave-message');
                collaborationAlert.querySelector('div').appendChild(autoSaveMessage);
                
                // Add collaborator count to the collaborators list if not already there
                let countBadge = document.getElementById('collaborator-count');
                if (!countBadge) {
                    countBadge = document.createElement('span');
                    countBadge.id = 'collaborator-count';
                    countBadge.className = 'badge bg-primary ms-1';
                    countBadge.style.fontSize = '0.7rem';
                    collaboratorsList.appendChild(countBadge);
                }
                countBadge.textContent = otherCollaboratorsCount > 1 ? `+${otherCollaboratorsCount - 1} more` : '';
                countBadge.style.display = otherCollaboratorsCount > 3 ? 'inline-block' : 'none';
                
                // Hide AutoSave spinner
                autoSaveSpinner.classList.add('d-none');
                
                // Show save status
                showSaveStatus('Collaborative mode active. Use Save button to save changes.', 'info');
            } else {
                // Re-enable AutoSave when there are no other collaborators
                autoSaveEnabled = true;
                
                // Remove the AutoSave message if it exists
                const autoSaveMessage = collaborationAlert.querySelector('.autosave-message');
                if (autoSaveMessage) {
                    autoSaveMessage.remove();
                }
                
                // Remove the collaborator count badge if it exists
                const countBadge = document.getElementById('collaborator-count');
                if (countBadge) {
                    countBadge.remove();
                }
                
                // Hide collaboration alert if there are no shared users
                if (!{{ $note->sharedWith->count() > 0 ? 'true' : 'false' }}) {
                    collaborationAlert.classList.add('d-none');
                }
            }
        }
        
        // Handle autosave
        function autoSave() {
            if (!autoSaveEnabled) return;

            if (isDirty) {
                saveNote();
            }
        }

        // Save note function with offline support
        function saveNote(manualSave = false) {
            // Don't save if autoSaveEnabled is false and this is not a manual save
            // This prevents auto-saving during collaborative editing
            if (!autoSaveEnabled && !manualSave && collaborationActive) return;
            
            if (!isDirty) return;
            if (!title.value.trim() || !content.value.trim()) return;
            
            showSaveStatus('Saving...', 'info', true);
            
            const noteData = {
                id: currentNoteId,
                title: title.value,
                content: content.value,
                updated_at: new Date().toISOString()
            };
            
            // Check for offline manager first
            if (window.offlineManager && navigator.onLine === false) {
                // We're offline and have the offline manager, save locally
                window.offlineManager.saveNote(noteData)
                    .then(result => {
                        if (result.success) {
                            isDirty = false;
                            showSaveStatus('Saved offline! Will sync when back online.', 'warning');
                        } else {
                            showSaveStatus('Error: ' + result.message, 'danger');
                        }
                    })
                    .catch(error => {
                        console.error('Error saving offline:', error);
                        showSaveStatus('Error saving offline: ' + error.message, 'danger');
                    });
                return;
            }
            
            // If we're here, either we're online or don't have the offline manager
            // Use fetch API to send the data
            const formData = new FormData();
            formData.append('_token', document.querySelector('input[name="_token"]').value);
            formData.append('_method', 'PUT');
            formData.append('title', title.value);
            formData.append('content', content.value);
            formData.append('_autosave', '1');
            
            // Add color from hidden input
            const colorInput = document.getElementById('color');
            if (colorInput) {
                formData.append('color', colorInput.value);
            }
            
            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    isDirty = false;
                    showSaveStatus('Saved successfully!', 'success');
                    
                    // Also save to local cache if we have the offline manager
                    if (window.offlineManager) {
                        const noteToCache = {
                            id: currentNoteId,
                            title: title.value,
                            content: content.value,
                            updated_at: new Date().toISOString()
                        };
                        window.offlineManager.saveToCache('cachedNotes', noteToCache)
                            .catch(error => {
                                console.error('Error caching note locally:', error);
                            });
                    }
                } else {
                    showSaveStatus('Error: ' + (data.message || 'Could not save'), 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                
                // If network error and we have offline manager, try to save offline
                if (window.offlineManager) {
                    window.offlineManager.saveNote(noteData)
                        .then(result => {
                            if (result.success) {
                                isDirty = false;
                                showSaveStatus('Saved offline due to network error', 'warning');
                            } else {
                                showSaveStatus('Error: Could not save online or offline', 'danger');
                            }
                        })
                        .catch(err => {
                            showSaveStatus('Error: Complete save failure', 'danger');
                        });
                } else {
                    showSaveStatus('Error: Could not connect to server', 'danger');
                }
            });
        }
        
        // Real-time update of a specific field
        function sendRealTimeUpdate(field, value) {
            console.log(`Sending real-time update for ${field}`);
            const url = "{{ route('notes.real-time-update', $note) }}";
            const formData = new FormData();
            
            // Get CSRF token
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            formData.append('_token', token);
            
            // Append field and value
            formData.append(field, value);
            
            // Always set broadcast_only to 1 for real-time updates
            // This ensures changes are broadcasted but not saved during typing
            formData.append('_broadcast_only', '1');
            
            // Debug data being sent
            console.log(`Sending update - field: ${field}, socketId: ${window.Echo?.socketId() || 'not available'}`);
            
            fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Socket-ID': window.Echo?.socketId() || '',
                    'X-CSRF-TOKEN': token
                },
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server responded with ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Real-time update response:', data);
            })
            .catch(error => {
                console.error('Error sending real-time update:', error);
            });
        }
        
        function doneTyping() {
            console.log(`doneTyping called - autoSaveEnabled: ${autoSaveEnabled}, collaborationActive: ${collaborationActive}`);
            if (autoSaveEnabled || !collaborationActive) {
                saveNote();
            }
        }
        
        // Throttle function to limit how often we send real-time updates
        function throttle(callback, delay) {
            let lastCall = 0;
            return function(...args) {
                const now = new Date().getTime();
                if (now - lastCall < delay) return;
                lastCall = now;
                return callback(...args);
            }
        }
        
        // Throttled version of real-time updates (50ms for smoother real-time experience)
        const throttledTitleUpdate = throttle(function() {
            if (!isProcessingExternalUpdate) {
                sendRealTimeUpdate('title', title.value);
            }
        }, 50);
        
        const throttledContentUpdate = throttle(function() {
            if (!isProcessingExternalUpdate) {
                sendRealTimeUpdate('content', content.value);
            }
        }, 50);
        
        // Update the collaborators list
        function updateCollaboratorsList() {
            collaboratorsList.innerHTML = '';
            const collaboratorCount = Object.keys(collaborators).length;
            console.log(`Updating collaborators list. Count: ${collaboratorCount}`);
            
            // Log the collaborators to help debug
            console.log('All collaborators:', collaborators);
            
            // Current user ID
            const currentUserId = {{ Auth::id() }};
            
            // Display the current user first if in collaborative mode
            if (collaborationActive && collaborators[currentUserId]) {
                // Create the current user's avatar with "You" label
                createCollaboratorAvatar(collaborators[currentUserId], true);
            }
            
            // Then display other users
            Object.values(collaborators).forEach(user => {
                // Skip current user as we've already added them
                if (user.id === currentUserId) return;
                
                createCollaboratorAvatar(user, false);
            });
            
            // Update collaboration status
            updateCollaborationStatus();
            console.log(`AutoSave enabled: ${autoSaveEnabled}`);
        }
        
        // Create a collaborator avatar
        function createCollaboratorAvatar(user, isCurrentUser) {
            const avatarContainer = document.createElement('div');
            avatarContainer.className = 'collaborator-container d-inline-block position-relative me-1';
            avatarContainer.style.width = '30px';
            avatarContainer.style.height = '30px';
            
            const avatar = document.createElement('span');
            avatar.className = 'collaborator-avatar rounded-circle d-inline-block text-white';
            avatar.style.width = '100%';
            avatar.style.height = '100%';
            avatar.style.transition = 'transform 0.2s ease, box-shadow 0.2s ease';
            avatar.style.cursor = 'pointer';
            avatar.style.border = '2px solid ' + (isCurrentUser ? '#28a745' : 'transparent'); // Green border for current user
            
            // Check for avatar URL in multiple possible properties
            const avatarUrl = user.avatar_url || null;
            console.log(`User ${user.name} avatar URL:`, avatarUrl);
            
            if (avatarUrl) {
                // Create an image element for the avatar
                const img = document.createElement('img');
                img.src = avatarUrl;
                img.className = 'rounded-circle';
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = 'cover';
                avatar.appendChild(img);
            } else {
                // Use a colored circle with the first letter of the user's name
                avatar.style.backgroundColor = getColorForUser(user.id);
                avatar.style.textAlign = 'center';
                avatar.style.lineHeight = '26px';
                avatar.textContent = user.name.charAt(0).toUpperCase();
            }
            
            // Add hover tooltip
            const tooltip = document.createElement('div');
            tooltip.className = 'position-absolute bg-dark text-white p-1 rounded invisible';
            tooltip.textContent = isCurrentUser ? 'You' : user.name + ' is editing';
            tooltip.style.bottom = '-30px';
            tooltip.style.left = '50%';
            tooltip.style.transform = 'translateX(-50%)';
            tooltip.style.whiteSpace = 'nowrap';
            tooltip.style.fontSize = '0.8rem';
            tooltip.style.zIndex = '1000';
            tooltip.style.opacity = '0';
            tooltip.style.transition = 'opacity 0.2s ease';
            
            // Add event listeners for hover effect
            avatarContainer.addEventListener('mouseenter', () => {
                avatar.style.transform = 'scale(1.1)';
                avatar.style.boxShadow = '0 0 5px rgba(0,0,0,0.3)';
                avatar.style.borderColor = isCurrentUser ? '#28a745' : '#fff';
                tooltip.classList.remove('invisible');
                tooltip.style.opacity = '1';
            });
            
            avatarContainer.addEventListener('mouseleave', () => {
                avatar.style.transform = 'scale(1)';
                avatar.style.boxShadow = 'none';
                avatar.style.borderColor = isCurrentUser ? '#28a745' : 'transparent';
                tooltip.classList.add('invisible');
                tooltip.style.opacity = '0';
            });
            
            avatarContainer.appendChild(avatar);
            avatarContainer.appendChild(tooltip);
            collaboratorsList.appendChild(avatarContainer);
        }
        
        // Get a consistent color for a user
        function getColorForUser(userId) {
            const colors = [
                '#007bff', '#6610f2', '#6f42c1', '#e83e8c', 
                '#dc3545', '#fd7e14', '#28a745', '#20c997', 
                '#17a2b8', '#6c757d'
            ];
            return colors[userId % colors.length];
        }
        
        // Set up event listeners for auto-saving
        title.addEventListener('input', function() {
            isDirty = true;
            clearTimeout(typingTimer);
            typingTimer = setTimeout(doneTyping, doneTypingInterval);
            showSaveStatus('Unsaved changes...');
            
            // Send real-time update immediately 
            throttledTitleUpdate();
        });
        
        content.addEventListener('input', function() {
            isDirty = true;
            clearTimeout(typingTimer);
            typingTimer = setTimeout(doneTyping, doneTypingInterval);
            showSaveStatus('Unsaved changes...');
            
            // Send real-time update immediately
            throttledContentUpdate();
        });
        
        // Save on blur events as well, but only if autosave is enabled
        title.addEventListener('blur', function() {
            if (autoSaveEnabled || !collaborationActive) {
                saveNote();
            }
        });
        
        content.addEventListener('blur', function() {
            if (autoSaveEnabled || !collaborationActive) {
                saveNote();
            }
        });
        
        // Function to explicitly handle leaving the edit session
        function leaveEditSession() {
            // Stop the heartbeat timer
            if (heartbeatTimer) {
                clearInterval(heartbeatTimer);
            }
            
            // Send a request to the server to broadcast that this user has left
            const leaveUrl = "{{ route('notes.leave-edit-session', $note) }}";
            
            // Use sendBeacon for more reliable delivery during page unload
            if (navigator.sendBeacon) {
                const data = new FormData();
                data.append('_token', document.querySelector('input[name="_token"]').value);
                navigator.sendBeacon(leaveUrl, data);
            } else {
                // Fallback to fetch with keepalive
                fetch(leaveUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                        'Accept': 'application/json',
                        'X-Socket-ID': window.Echo?.socketId() || ''
                    },
                    keepalive: true // This ensures the request completes even if page unloads
                }).catch(error => console.error('Error leaving session:', error));
            }
        }

        // Clean up resources when the page is being unloaded
        window.addEventListener('beforeunload', function(e) {
            // Explicitly mark we are leaving
            leaveEditSession();
            
            // Handle unsaved changes
            if (isDirty) {
                saveNote();
                // Modern browsers no longer show custom messages, but we'll set one anyway
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });

        // Also handle visibilitychange for when browser tab is hidden/inactive
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden') {
                // If user switches tabs or minimizes browser, they may not return
                // We'll set a timeout to mark them as "inactive" after a while
                setTimeout(() => {
                    if (document.visibilityState === 'hidden') {
                        leaveEditSession();
                    }
                }, userActivityTimeout);
            } else if (document.visibilityState === 'visible') {
                // User came back, restart heartbeat
                startHeartbeat();
            }
        });

        // Add event listener to the form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            saveNote(true); // Pass true to indicate a manual save
            isDirty = false; // Reset dirty flag when manually saving
        });
        
        // Debug logging for Echo connection
        console.log('Setting up Echo presence channel for note.' + currentNoteId);
        
        try {
            // Set up Echo for real-time collaboration
            if (!window.Echo) {
                throw new Error('Echo is not initialized');
            }
            
            // Force disconnect and reconnect to ensure fresh connection
            window.Echo.connector.pusher.disconnect();
            setTimeout(() => {
                window.Echo.connector.pusher.connect();
            }, 500);
            
            const channelName = `note.${currentNoteId}`;
            console.log(`Attempting to join channel: ${channelName}`);
            
            const channel = window.Echo.join(channelName);
            console.log('Successfully joined Echo channel for note.' + currentNoteId);
            
            // Debug channel subscription
            channel.listenForWhisper('typing', function(e) {
                console.log('Whisper received:', e);
            });
            
            // Test whisper
            setTimeout(() => {
                console.log('Sending test whisper');
                channel.whisper('typing', {
                    user: '{{ Auth::user()->name }}',
                    message: 'Test connection'
                });
            }, 2000);
            
            // Manually add current user to collaborators - fix for avatar display
            // Need to do this because the current user's data is not provided in the 'here' callback
            const currentUser = {
                id: {{ Auth::id() }},
                name: "{{ Auth::user()->name }}",
                avatar_url: "{{ Auth::user()->avatar_url }}",
                lastSeen: Date.now()
            };
            console.log('Current user data:', currentUser);
            
            channel
                .here(users => {
                    console.log('Users currently editing this note:', users);
                    const otherUsers = users.filter(user => user.id !== {{ Auth::id() }});
                    console.log(`Found ${otherUsers.length} other users editing this note`);
                    
                    // Add current user first
                    collaborators[currentUser.id] = currentUser;
                    
                    // Then add other users
                    otherUsers.forEach(user => {
                        // Add lastSeen timestamp to track user activity
                        user.lastSeen = Date.now();
                        collaborators[user.id] = user;
                    });
                    updateCollaboratorsList();
                })
                .joining(user => {
                    console.log('User joined:', user);
                    if (user.id !== {{ Auth::id() }}) {
                        // Add animation class to highlight the collaborators list
                        collaboratorsList.classList.add('highlight-pulse');
                        setTimeout(() => {
                            collaboratorsList.classList.remove('highlight-pulse');
                        }, 2000);
                        
                        // Add user to collaborators with current timestamp
                        user.lastSeen = Date.now();
                        collaborators[user.id] = user;
                        updateCollaboratorsList();
                        
                        // Show notification that someone joined with dark mode support
                        const toast = document.createElement('div');
                        toast.className = 'toast show position-fixed bottom-0 end-0 m-3 collaboration-toast';
                        toast.setAttribute('role', 'alert');
                        toast.setAttribute('aria-live', 'assertive');
                        toast.setAttribute('aria-atomic', 'true');
                        toast.style.zIndex = '9999';
                        
                        toast.innerHTML = `
                            <div class="toast-header">
                                <strong class="me-auto">Collaboration</strong>
                                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                            </div>
                            <div class="toast-body">
                                ${user.name} has joined the editing session.
                            </div>
                        `;
                        
                        document.body.appendChild(toast);
                        
                        // Remove the toast after 3 seconds
                        setTimeout(() => {
                            toast.remove();
                        }, 3000);
                    }
                })
                .leaving(user => {
                    console.log('User left:', user);
                    if (user.id !== {{ Auth::id() }}) {
                        // Add animation class to highlight the collaborators list
                        collaboratorsList.classList.add('highlight-pulse');
                        setTimeout(() => {
                            collaboratorsList.classList.remove('highlight-pulse');
                        }, 2000);
                        
                        // Remove user from collaborators
                        delete collaborators[user.id];
                        updateCollaboratorsList();
                        
                        // Show notification that someone left with dark mode support
                        const toast = document.createElement('div');
                        toast.className = 'toast show position-fixed bottom-0 end-0 m-3 collaboration-toast';
                        toast.setAttribute('role', 'alert');
                        toast.setAttribute('aria-live', 'assertive');
                        toast.setAttribute('aria-atomic', 'true');
                        toast.style.zIndex = '9999';
                        
                        toast.innerHTML = `
                            <div class="toast-header">
                                <strong class="me-auto">Collaboration</strong>
                                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                            </div>
                            <div class="toast-body">
                                ${user.name} has left the editing session.
                            </div>
                        `;
                        
                        document.body.appendChild(toast);
                        
                        // Remove the toast after 3 seconds
                        setTimeout(() => {
                            toast.remove();
                        }, 3000);
                    }
                });

            // Listen for note title updates
            channel.listen('.note.title.updated', function(e) {
                console.log('Title update event received:', e);
                // Only update if the change came from someone else
                if (e.userId !== {{ Auth::id() }}) {
                    isProcessingExternalUpdate = true;
                    
                    // Update the user's lastSeen timestamp
                    if (collaborators[e.userId]) {
                        collaborators[e.userId].lastSeen = Date.now();
                    } else {
                        // Add unknown user to collaborators list if not already there
                        console.log('Adding unknown user from title update:', e.userId, e.userName);
                        collaborators[e.userId] = {
                            id: e.userId,
                            name: e.userName,
                            avatar_url: e.userAvatarUrl,
                            lastSeen: Date.now()
                        };
                        updateCollaboratorsList();
                    }
                    
                    // Update the title value and force focus if user was already editing it
                    const hadFocus = document.activeElement === title;
                    const selectionStart = title.selectionStart;
                    const selectionEnd = title.selectionEnd;
                    
                    title.value = e.title;
                    
                    // Restore focus and selection if needed
                    if (hadFocus) {
                        title.focus();
                        // Try to maintain cursor position if possible
                        try {
                            title.setSelectionRange(selectionStart, selectionEnd);
                        } catch (e) {
                            // Silently fail if we can't restore selection
                        }
                    }
                    
                    // Show update notification
                    showSaveStatus(`${e.userName} updated the title`, 'info');
                    
                    isProcessingExternalUpdate = false;
                }
            });

            // Listen for note content updates
            channel.listen('.note.content.updated', function(e) {
                console.log('Content update event received:', e);
                // Only update if the change came from someone else
                if (e.userId !== {{ Auth::id() }}) {
                    isProcessingExternalUpdate = true;
                    
                    // Update the user's lastSeen timestamp
                    if (collaborators[e.userId]) {
                        collaborators[e.userId].lastSeen = Date.now();
                    } else {
                        // Add unknown user to collaborators list if not already there
                        console.log('Adding unknown user from content update:', e.userId, e.userName);
                        collaborators[e.userId] = {
                            id: e.userId,
                            name: e.userName,
                            avatar_url: e.userAvatarUrl,
                            lastSeen: Date.now()
                        };
                        updateCollaboratorsList();
                    }
                    
                    // Save current focus and selection state
                    const hadFocus = document.activeElement === content;
                    const selectionStart = content.selectionStart;
                    const selectionEnd = content.selectionEnd;
                    
                    // Set the content value
                    content.value = e.content;
                    
                    // Restore focus and selection if needed
                    if (hadFocus) {
                        content.focus();
                        // Try to maintain cursor position if possible
                        try {
                            content.setSelectionRange(selectionStart, selectionEnd);
                        } catch (e) {
                            // Silently fail if we can't restore selection
                        }
                    }
                    
                    // Trigger an input event to ensure any dynamic content handlers fire
                    const inputEvent = new Event('input', { bubbles: true });
                    content.dispatchEvent(inputEvent);
                    
                    // Show update notification
                    showSaveStatus(`${e.userName} updated the content`, 'info');
                    
                    isProcessingExternalUpdate = false;
                }
            });

            // Listen for user left edit session
            channel.listen('.user.left.edit.session', (e) => {
                console.log('User left edit session event received:', e);
                // Only update if the event is about someone else
                if (e.userId !== {{ Auth::id() }}) {
                    // Remove user from collaborators
                    delete collaborators[e.userId];
                    updateCollaboratorsList();
                    
                    // Show notification that someone left
                    const toast = document.createElement('div');
                    toast.className = 'toast show position-fixed bottom-0 end-0 m-3 collaboration-toast';
                    toast.setAttribute('role', 'alert');
                    toast.setAttribute('aria-live', 'assertive');
                    toast.setAttribute('aria-atomic', 'true');
                    toast.style.zIndex = '9999';
                    
                    toast.innerHTML = `
                        <div class="toast-header">
                            <strong class="me-auto">Collaboration</strong>
                            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                        <div class="toast-body">
                            ${e.userName} has left the editing session.
                        </div>
                    `;
                    
                    document.body.appendChild(toast);
                    
                    // Remove the toast after 3 seconds
                    setTimeout(() => {
                        toast.remove();
                    }, 3000);
                }
            });
            
            // Function to periodically check for inactive users
            function checkInactiveUsers() {
                const inactiveThreshold = 60000; // 60 seconds
                const now = Date.now();
                
                // Check if any user has been inactive for too long
                Object.keys(collaborators).forEach(userId => {
                    // Skip current user
                    if (parseInt(userId) === {{ Auth::id() }}) return;
                    
                    // If we didn't get an update from this user for a while, consider them disconnected
                    if (!collaborators[userId].lastSeen || now - collaborators[userId].lastSeen > inactiveThreshold) {
                        console.log(`User ${collaborators[userId].name} seems to be inactive, removing them`);
                        
                        // Show notification that someone left
                        const toast = document.createElement('div');
                        toast.className = 'toast show position-fixed bottom-0 end-0 m-3 collaboration-toast';
                        toast.setAttribute('role', 'alert');
                        toast.setAttribute('aria-live', 'assertive');
                        toast.setAttribute('aria-atomic', 'true');
                        toast.style.zIndex = '9999';
                        
                        toast.innerHTML = `
                            <div class="toast-header">
                                <strong class="me-auto">Collaboration</strong>
                                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                            </div>
                            <div class="toast-body">
                                ${collaborators[userId].name} seems to have disconnected.
                            </div>
                        `;
                        
                        document.body.appendChild(toast);
                        
                        // Remove the toast after 3 seconds
                        setTimeout(() => {
                            toast.remove();
                        }, 3000);
                        
                        // Remove user from collaborators list
                        delete collaborators[userId];
                        updateCollaboratorsList();
                    }
                });
            }

            // Set up a timer to check for inactive users every 30 seconds
            setInterval(checkInactiveUsers, 30000);

            console.log('Echo channel setup complete');
        } catch (error) {
            console.error('Error setting up Echo:', error);
            showSaveStatus('Error setting up real-time collaboration', 'danger');
        }

        // Set up event listeners for manual save button
        document.querySelector('.save-button').addEventListener('click', function(e) {
            e.preventDefault();
            saveNote(true);
        });

        // Function to send a heartbeat to let the server know you're still active
        function sendHeartbeat() {
            const heartbeatUrl = "{{ route('notes.heartbeat', $note) }}";
            fetch(heartbeatUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                    'Accept': 'application/json',
                    'X-Socket-ID': window.Echo?.socketId() || ''
                }
            }).catch(error => console.error('Error sending heartbeat:', error));
            
            lastHeartbeat = Date.now();
        }
        
        // Start heartbeat when page loads
        function startHeartbeat() {
            // Send initial heartbeat
            sendHeartbeat();
            
            // Set interval to send heartbeat periodically
            heartbeatTimer = setInterval(sendHeartbeat, heartbeatInterval);
        }
        
        // Start heartbeat when page loads
        startHeartbeat();
        
        // Reset the heartbeat timer on user activity
        function resetHeartbeatTimer() {
            lastHeartbeat = Date.now();
        }
        
        // Listen for user activity events
        ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(eventType => {
            document.addEventListener(eventType, resetHeartbeatTimer);
        });

        // Listen for any events on the channel for debugging
        for (let evt of ['.note.title.updated', '.note.content.updated', '.user.left.edit.session']) {
            channel.listen(evt, function(e) {
                console.log(`DEBUG - Event ${evt} received:`, e);
            });
        }

        // Test direct Pusher connection to verify it's working
        console.log('Testing direct Pusher connection...');
        
        const PUSHER_APP_KEY = "{{ config('broadcasting.connections.pusher.key') }}";
        const PUSHER_APP_CLUSTER = "{{ config('broadcasting.connections.pusher.options.cluster') }}";
        
        // Create a new Pusher instance
        const testPusher = new Pusher(PUSHER_APP_KEY, {
            cluster: PUSHER_APP_CLUSTER,
            forceTLS: true
        });
        
        // Subscribe to a test channel
        const testChannel = testPusher.subscribe('test-channel');
        
        // Log connection status
        testPusher.connection.bind('connected', function() {
            console.log('✅ Test Pusher connection successful');
        });
        
        testPusher.connection.bind('failed', function(error) {
            console.error('❌ Test Pusher connection failed:', error);
        });
        
        // Disconnect test pusher after 10 seconds to avoid unnecessary connections
        setTimeout(() => {
            testPusher.disconnect();
        }, 10000);
    });
</script>
@endpush
@endsection 