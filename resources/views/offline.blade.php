@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header text-center">
                    <i class="fas fa-wifi-slash fa-2x text-muted mb-2"></i>
                    <h4>You're Offline</h4>
                </div>

                <div class="card-body">
                    <div class="text-center mb-4">
                        <p class="lead">You are currently offline and this content isn't available in your cache.</p>
                        <p>Don't worry, your previously viewed notes are still accessible:</p>
                    </div>
                    
                    <div id="offline-notes-container" class="mt-4">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Checking for cached notes...</p>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-4">
                        <i class="fas fa-info-circle me-2"></i>
                        <span>Any changes you make while offline will be synchronized once you're back online.</span>
                    </div>
                    
                    <div class="d-grid gap-2 col-md-6 mx-auto mt-4">
                        <button id="retry-connection" class="btn btn-primary">
                            <i class="fas fa-sync-alt me-2"></i> Try Again
                        </button>
                        <a href="/" class="btn btn-outline-secondary">
                            <i class="fas fa-home me-2"></i> Go to Homepage
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get references to elements
    const offlineNotesContainer = document.getElementById('offline-notes-container');
    const retryButton = document.getElementById('retry-connection');
    
    // Load offline notes from IndexedDB
    loadOfflineNotes();
    
    // Add event listener for retry button
    retryButton.addEventListener('click', function() {
        window.location.reload();
    });
    
    // Function to load notes from IndexedDB
    async function loadOfflineNotes() {
        if (!('indexedDB' in window)) {
            displayMessage('Your browser does not support offline storage.');
            return;
        }
        
        try {
            const db = await openNotesDB();
            const notes = await db.getAll('cachedNotes');
            
            if (notes && notes.length > 0) {
                displayNotes(notes);
            } else {
                displayMessage('No saved notes found. Once you\'re back online, you\'ll be able to access your notes again.');
            }
        } catch (error) {
            console.error('Error loading offline notes:', error);
            displayMessage('Failed to load offline notes: ' + error.message);
        }
    }
    
    // Function to display notes
    function displayNotes(notes) {
        // Clear loading spinner
        offlineNotesContainer.innerHTML = '';
        
        // Create heading
        const heading = document.createElement('h5');
        heading.textContent = 'Your Cached Notes';
        heading.className = 'mb-3';
        offlineNotesContainer.appendChild(heading);
        
        // Create note list
        const noteList = document.createElement('div');
        noteList.className = 'list-group';
        
        notes.forEach(note => {
            const noteItem = document.createElement('a');
            noteItem.href = '#';
            noteItem.className = 'list-group-item list-group-item-action';
            noteItem.innerHTML = `
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1">${escapeHtml(note.title)}</h6>
                    <small>${formatDate(note.updated_at)}</small>
                </div>
                <p class="mb-1 text-truncate">${escapeHtml(note.content.substring(0, 100))}</p>
            `;
            
            // Open the note in the offline viewer when clicked
            noteItem.addEventListener('click', function(e) {
                e.preventDefault();
                viewOfflineNote(note);
            });
            
            noteList.appendChild(noteItem);
        });
        
        offlineNotesContainer.appendChild(noteList);
    }
    
    // Function to display an offline note
    function viewOfflineNote(note) {
        // Clear container
        offlineNotesContainer.innerHTML = '';
        
        // Create back button
        const backBtn = document.createElement('button');
        backBtn.className = 'btn btn-sm btn-outline-secondary mb-3';
        backBtn.innerHTML = '<i class="fas fa-arrow-left me-2"></i>Back to List';
        backBtn.addEventListener('click', function() {
            loadOfflineNotes();
        });
        offlineNotesContainer.appendChild(backBtn);
        
        // Create note view
        const noteView = document.createElement('div');
        noteView.className = 'card';
        noteView.innerHTML = `
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>${escapeHtml(note.title)}</h5>
                <span class="badge bg-secondary">Offline View</span>
            </div>
            <div class="card-body">
                <div class="note-content">${escapeHtml(note.content).replace(/\n/g, '<br>')}</div>
                <div class="mt-3 text-muted">
                    <small>Last updated: ${formatDate(note.updated_at)}</small>
                </div>
            </div>
        `;
        
        offlineNotesContainer.appendChild(noteView);
    }
    
    // Helper function to display a message
    function displayMessage(message) {
        offlineNotesContainer.innerHTML = `
            <div class="alert alert-secondary text-center">
                <i class="fas fa-info-circle me-2"></i>
                ${message}
            </div>
        `;
    }
    
    // Helper function to escape HTML
    function escapeHtml(str) {
        if (!str) return '';
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    
    // Helper function to format date
    function formatDate(dateStr) {
        if (!dateStr) return 'Unknown date';
        const date = new Date(dateStr);
        return date.toLocaleString();
    }
    
    // Helper function to open IndexedDB
    function openNotesDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open('NotesAppDB', 1);
            
            request.onerror = event => {
                reject(new Error('Database error: ' + event.target.errorCode));
            };
            
            request.onupgradeneeded = event => {
                const db = event.target.result;
                // Create object stores if they don't exist
                if (!db.objectStoreNames.contains('cachedNotes')) {
                    db.createObjectStore('cachedNotes', { keyPath: 'id' });
                }
                if (!db.objectStoreNames.contains('pendingChanges')) {
                    db.createObjectStore('pendingChanges', { keyPath: 'id', autoIncrement: true });
                }
            };
            
            request.onsuccess = event => {
                const db = event.target.result;
                resolve({
                    getAll: storeName => {
                        return new Promise((resolve, reject) => {
                            const transaction = db.transaction(storeName, 'readonly');
                            const store = transaction.objectStore(storeName);
                            const request = store.getAll();
                            
                            request.onsuccess = () => {
                                resolve(request.result);
                            };
                            
                            request.onerror = () => {
                                reject(request.error);
                            };
                        });
                    },
                    get: (storeName, key) => {
                        return new Promise((resolve, reject) => {
                            const transaction = db.transaction(storeName, 'readonly');
                            const store = transaction.objectStore(storeName);
                            const request = store.get(key);
                            
                            request.onsuccess = () => {
                                resolve(request.result);
                            };
                            
                            request.onerror = () => {
                                reject(request.error);
                            };
                        });
                    }
                });
            };
        });
    }
});
</script>
@endpush
@endsection 