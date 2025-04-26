// IndexedDB implementation for Notes App
// This handles local storage of notes for offline use

class NotesDB {
    constructor() {
        this.dbName = 'notes-db';
        this.dbVersion = 1;
        this.notesStoreName = 'notes';
        this.labelsStoreName = 'labels';
        this.pendingChangesStoreName = 'pending-changes';
        this.db = null;
        this.isInitialized = false;
        this.syncInProgress = false;
        this.offlineChanges = [];
        
        // Initialize the database
        this.init();
    }
    
    // Initialize the IndexedDB database
    async init() {
        if (this.isInitialized) return Promise.resolve(this.db);
        
        return new Promise((resolve, reject) => {
            // Check if IndexedDB is supported
            if (!('indexedDB' in window)) {
                console.error('This browser doesn\'t support IndexedDB');
                reject(new Error('IndexedDB not supported'));
                return;
            }
            
            const request = indexedDB.open(this.dbName, this.dbVersion);
            
            // Create object stores on database upgrade
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // Create notes store with auto-incrementing ID
                if (!db.objectStoreNames.contains(this.notesStoreName)) {
                    const notesStore = db.createObjectStore(this.notesStoreName, { keyPath: 'id' });
                    notesStore.createIndex('user_id', 'user_id', { unique: false });
                    notesStore.createIndex('updated_at', 'updated_at', { unique: false });
                }
                
                // Create labels store
                if (!db.objectStoreNames.contains(this.labelsStoreName)) {
                    const labelsStore = db.createObjectStore(this.labelsStoreName, { keyPath: 'id' });
                    labelsStore.createIndex('user_id', 'user_id', { unique: false });
                }
                
                // Create store for pending changes that need to be synced
                if (!db.objectStoreNames.contains(this.pendingChangesStoreName)) {
                    const pendingStore = db.createObjectStore(this.pendingChangesStoreName, { 
                        keyPath: 'id', 
                        autoIncrement: true 
                    });
                    pendingStore.createIndex('timestamp', 'timestamp', { unique: false });
                    pendingStore.createIndex('entityType', 'entityType', { unique: false });
                    pendingStore.createIndex('action', 'action', { unique: false });
                }
            };
            
            // Handle successful database opening
            request.onsuccess = (event) => {
                this.db = event.target.result;
                this.isInitialized = true;
                console.log('Database initialized successfully');
                
                // Set up online/offline event listeners
                this.setupNetworkListeners();
                
                resolve(this.db);
            };
            
            // Handle errors
            request.onerror = (event) => {
                console.error('Error opening database:', event.target.error);
                reject(event.target.error);
            };
        });
    }
    
    // Set up listeners for online/offline events
    setupNetworkListeners() {
        window.addEventListener('online', () => {
            console.log('App is online. Syncing data...');
            this.syncWithServer();
        });
        
        window.addEventListener('offline', () => {
            console.log('App is offline. Changes will be queued.');
            this.updateOfflineStatus(true);
        });
        
        // Check initial state
        if (navigator.onLine) {
            this.updateOfflineStatus(false);
        } else {
            this.updateOfflineStatus(true);
        }
    }
    
    // Update UI to reflect offline status
    updateOfflineStatus(isOffline) {
        // Update UI elements to show offline status
        const offlineIndicator = document.getElementById('offline-indicator');
        if (offlineIndicator) {
            offlineIndicator.style.display = isOffline ? 'block' : 'none';
        }
    }
    
    // Store notes in IndexedDB from server response
    async storeNotes(notes, userId) {
        await this.init();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(this.notesStoreName, 'readwrite');
            const store = transaction.objectStore(this.notesStoreName);
            
            // Process each note
            let notesProcessed = 0;
            
            notes.forEach(note => {
                // Add user_id if not present
                if (!note.user_id) {
                    note.user_id = userId;
                }
                
                // Ensure updated_at is a Date object
                if (note.updated_at && typeof note.updated_at === 'string') {
                    note.updated_at = new Date(note.updated_at);
                } else {
                    note.updated_at = new Date();
                }
                
                const request = store.put(note);
                
                request.onsuccess = () => {
                    notesProcessed++;
                    if (notesProcessed === notes.length) {
                        console.log(`Stored ${notesProcessed} notes in IndexedDB`);
                        resolve();
                    }
                };
                
                request.onerror = (event) => {
                    console.error('Error storing note:', event.target.error);
                    reject(event.target.error);
                };
            });
            
            // If no notes to process, resolve immediately
            if (notes.length === 0) {
                resolve();
            }
        });
    }
    
    // Store labels in IndexedDB
    async storeLabels(labels, userId) {
        await this.init();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(this.labelsStoreName, 'readwrite');
            const store = transaction.objectStore(this.labelsStoreName);
            
            // Process each label
            let labelsProcessed = 0;
            
            labels.forEach(label => {
                // Add user_id if not present
                if (!label.user_id) {
                    label.user_id = userId;
                }
                
                const request = store.put(label);
                
                request.onsuccess = () => {
                    labelsProcessed++;
                    if (labelsProcessed === labels.length) {
                        console.log(`Stored ${labelsProcessed} labels in IndexedDB`);
                        resolve();
                    }
                };
                
                request.onerror = (event) => {
                    console.error('Error storing label:', event.target.error);
                    reject(event.target.error);
                };
            });
            
            // If no labels to process, resolve immediately
            if (labels.length === 0) {
                resolve();
            }
        });
    }
    
    // Get all notes from IndexedDB
    async getNotes(userId) {
        await this.init();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(this.notesStoreName, 'readonly');
            const store = transaction.objectStore(this.notesStoreName);
            const index = store.index('user_id');
            
            const request = index.getAll(userId);
            
            request.onsuccess = () => {
                const notes = request.result;
                resolve(notes);
            };
            
            request.onerror = (event) => {
                console.error('Error retrieving notes:', event.target.error);
                reject(event.target.error);
            };
        });
    }
    
    // Get a single note by ID
    async getNote(noteId) {
        await this.init();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(this.notesStoreName, 'readonly');
            const store = transaction.objectStore(this.notesStoreName);
            
            const request = store.get(parseInt(noteId, 10));
            
            request.onsuccess = () => {
                const note = request.result;
                resolve(note);
            };
            
            request.onerror = (event) => {
                console.error('Error retrieving note:', event.target.error);
                reject(event.target.error);
            };
        });
    }
    
    // Get all labels from IndexedDB
    async getLabels(userId) {
        await this.init();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(this.labelsStoreName, 'readonly');
            const store = transaction.objectStore(this.labelsStoreName);
            const index = store.index('user_id');
            
            const request = index.getAll(userId);
            
            request.onsuccess = () => {
                const labels = request.result;
                resolve(labels);
            };
            
            request.onerror = (event) => {
                console.error('Error retrieving labels:', event.target.error);
                reject(event.target.error);
            };
        });
    }
    
    // Add a pending change to be synced later
    async addPendingChange(entityType, entityId, action, data) {
        await this.init();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(this.pendingChangesStoreName, 'readwrite');
            const store = transaction.objectStore(this.pendingChangesStoreName);
            
            const change = {
                entityType, // 'note', 'label', etc.
                entityId,   // ID of the entity
                action,     // 'create', 'update', 'delete'
                data,       // Any data needed for the action
                timestamp: Date.now()
            };
            
            const request = store.add(change);
            
            request.onsuccess = () => {
                console.log('Pending change added:', change);
                this.offlineChanges.push(change);
                resolve(request.result);
            };
            
            request.onerror = (event) => {
                console.error('Error adding pending change:', event.target.error);
                reject(event.target.error);
            };
        });
    }
    
    // Create a note while offline
    async createNoteOffline(noteData, userId) {
        // Generate a temporary ID for the note (negative to avoid conflicts)
        const tempId = -Math.floor(Math.random() * 1000000) - 1;
        
        const note = {
            id: tempId,
            user_id: userId,
            title: noteData.title,
            content: noteData.content,
            color: noteData.color || '#ffffff',
            pinned: noteData.pinned || false,
            created_at: new Date(),
            updated_at: new Date(),
            temp_id: noteData.temp_id,
            is_offline_created: true
        };
        
        // Store the note in IndexedDB
        await this.storeNotes([note], userId);
        
        // Add a pending change
        await this.addPendingChange('note', tempId, 'create', note);
        
        return note;
    }
    
    // Update a note while offline
    async updateNoteOffline(noteId, noteData) {
        // Get the current note
        let note = await this.getNote(noteId);
        
        if (!note) {
            throw new Error('Note not found');
        }
        
        // Update the note data
        Object.assign(note, noteData, { updated_at: new Date() });
        
        // Store the updated note
        await this.storeNotes([note], note.user_id);
        
        // Add a pending change
        await this.addPendingChange('note', noteId, 'update', note);
        
        return note;
    }
    
    // Delete a note while offline
    async deleteNoteOffline(noteId) {
        // Get the note first to have its data for sync
        const note = await this.getNote(noteId);
        
        if (!note) {
            throw new Error('Note not found');
        }
        
        await this.init();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(this.notesStoreName, 'readwrite');
            const store = transaction.objectStore(this.notesStoreName);
            
            const request = store.delete(parseInt(noteId, 10));
            
            request.onsuccess = async () => {
                console.log('Note deleted from IndexedDB:', noteId);
                
                // Add a pending change
                await this.addPendingChange('note', noteId, 'delete', note);
                
                resolve();
            };
            
            request.onerror = (event) => {
                console.error('Error deleting note:', event.target.error);
                reject(event.target.error);
            };
        });
    }
    
    // Sync pending changes with the server
    async syncWithServer() {
        if (this.syncInProgress || !navigator.onLine) {
            return;
        }
        
        this.syncInProgress = true;
        
        try {
            await this.init();
            
            const transaction = this.db.transaction(this.pendingChangesStoreName, 'readonly');
            const store = transaction.objectStore(this.pendingChangesStoreName);
            
            const request = store.getAll();
            
            request.onsuccess = async () => {
                const pendingChanges = request.result;
                
                if (pendingChanges.length === 0) {
                    console.log('No pending changes to sync');
                    this.syncInProgress = false;
                    return;
                }
                
                console.log(`Syncing ${pendingChanges.length} pending changes`);
                
                // Sort changes by timestamp
                pendingChanges.sort((a, b) => a.timestamp - b.timestamp);
                
                // Process each change
                for (const change of pendingChanges) {
                    try {
                        await this.processPendingChange(change);
                        await this.removePendingChange(change.id);
                    } catch (error) {
                        console.error('Error processing change:', error);
                        // Continue with the next change
                    }
                }
                
                console.log('Sync completed successfully');
                this.syncInProgress = false;
            };
            
            request.onerror = (event) => {
                console.error('Error retrieving pending changes:', event.target.error);
                this.syncInProgress = false;
            };
        } catch (error) {
            console.error('Sync failed:', error);
            this.syncInProgress = false;
        }
    }
    
    // Process a single pending change
    async processPendingChange(change) {
        console.log('Processing change:', change);
        
        const { entityType, action, entityId, data } = change;
        
        let url, method, requestData;
        
        // Map entity types to API endpoints
        const endpoints = {
            note: '/notes',
            label: '/labels'
        };
        
        const baseUrl = window.location.origin;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        switch (action) {
            case 'create':
                url = `${baseUrl}${endpoints[entityType]}`;
                method = 'POST';
                requestData = data;
                break;
                
            case 'update':
                url = `${baseUrl}${endpoints[entityType]}/${Math.abs(entityId)}`;
                method = 'PUT';
                requestData = data;
                break;
                
            case 'delete':
                url = `${baseUrl}${endpoints[entityType]}/${Math.abs(entityId)}`;
                method = 'DELETE';
                requestData = {};
                break;
                
            default:
                throw new Error(`Unknown action: ${action}`);
        }
        
        // For created notes with temporary IDs
        if (action === 'create' && entityId < 0) {
            requestData.temp_offline_id = entityId;
        }
        
        // Make the request
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(requestData)
        });
        
        if (!response.ok) {
            throw new Error(`Request failed with status ${response.status}`);
        }
        
        const result = await response.json();
        
        // Handle specific post-processing
        if (action === 'create' && entityType === 'note') {
            // Update the local note with the server-generated ID
            await this.updateNoteAfterSync(entityId, result.note);
        }
        
        return result;
    }
    
    // Update a note after syncing with server
    async updateNoteAfterSync(oldId, newNote) {
        try {
            await this.init();
            
            // Delete the old note with temporary ID
            const transaction1 = this.db.transaction(this.notesStoreName, 'readwrite');
            const store1 = transaction1.objectStore(this.notesStoreName);
            await store1.delete(parseInt(oldId, 10));
            
            // Add the new note with server ID
            const transaction2 = this.db.transaction(this.notesStoreName, 'readwrite');
            const store2 = transaction2.objectStore(this.notesStoreName);
            await store2.add(newNote);
            
            console.log(`Note ID updated: ${oldId} -> ${newNote.id}`);
        } catch (error) {
            console.error('Error updating note after sync:', error);
        }
    }
    
    // Remove a pending change
    async removePendingChange(changeId) {
        await this.init();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(this.pendingChangesStoreName, 'readwrite');
            const store = transaction.objectStore(this.pendingChangesStoreName);
            
            const request = store.delete(changeId);
            
            request.onsuccess = () => {
                console.log('Pending change removed:', changeId);
                resolve();
            };
            
            request.onerror = (event) => {
                console.error('Error removing pending change:', event.target.error);
                reject(event.target.error);
            };
        });
    }
    
    // Clear all data (useful for logout)
    async clearAllData() {
        await this.init();
        
        return new Promise((resolve, reject) => {
            // Clear notes store
            const transaction1 = this.db.transaction(this.notesStoreName, 'readwrite');
            const notesStore = transaction1.objectStore(this.notesStoreName);
            const request1 = notesStore.clear();
            
            request1.onsuccess = () => {
                console.log('Notes store cleared');
                
                // Clear labels store
                const transaction2 = this.db.transaction(this.labelsStoreName, 'readwrite');
                const labelsStore = transaction2.objectStore(this.labelsStoreName);
                const request2 = labelsStore.clear();
                
                request2.onsuccess = () => {
                    console.log('Labels store cleared');
                    
                    // Clear pending changes store
                    const transaction3 = this.db.transaction(this.pendingChangesStoreName, 'readwrite');
                    const pendingStore = transaction3.objectStore(this.pendingChangesStoreName);
                    const request3 = pendingStore.clear();
                    
                    request3.onsuccess = () => {
                        console.log('Pending changes store cleared');
                        resolve();
                    };
                    
                    request3.onerror = (event) => {
                        console.error('Error clearing pending changes store:', event.target.error);
                        reject(event.target.error);
                    };
                };
                
                request2.onerror = (event) => {
                    console.error('Error clearing labels store:', event.target.error);
                    reject(event.target.error);
                };
            };
            
            request1.onerror = (event) => {
                console.error('Error clearing notes store:', event.target.error);
                reject(event.target.error);
            };
        });
    }
}

// Create and export an instance of the NotesDB class
const notesDB = new NotesDB();

// Make the database available globally
window.notesDB = notesDB; 