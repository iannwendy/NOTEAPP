/**
 * Offline Manager for Notes App
 * Handles offline storage, synchronization, and PWA functionality
 */

class OfflineManager {
    constructor() {
        this.db = null;
        this.isOnline = navigator.onLine;
        this.pendingSyncCount = 0;
        this.initDB();
        this.setupEventListeners();
        this.checkSyncStatus();
    }

    /**
     * Initialize the IndexedDB database
     */
    async initDB() {
        try {
            this.db = await this.openDatabase();
            console.log('IndexedDB initialized successfully');
            
            // Update UI badge with pending sync count
            this.updatePendingSyncCount();
        } catch (error) {
            console.error('Failed to initialize IndexedDB:', error);
        }
    }

    /**
     * Set up event listeners for online/offline status changes
     */
    setupEventListeners() {
        // Online/offline event listeners
        window.addEventListener('online', () => this.handleOnlineStatusChange(true));
        window.addEventListener('offline', () => this.handleOnlineStatusChange(false));
        
        // Listen for messages from service worker
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data && event.data.type === 'SYNC_REQUIRED') {
                this.syncData();
            }
        });
        
        // Listen for beforeunload to warn about unsaved changes
        window.addEventListener('beforeunload', (event) => {
            if (this.pendingSyncCount > 0) {
                const message = 'You have unsaved changes that haven\'t been synchronized yet. Are you sure you want to leave?';
                event.returnValue = message;
                return message;
            }
        });
    }

    /**
     * Handle online/offline status changes
     */
    async handleOnlineStatusChange(isOnline) {
        this.isOnline = isOnline;
        
        // Show notification
        this.showConnectivityNotification(isOnline);
        
        // If we're back online, start sync
        if (isOnline) {
            try {
                await this.syncData();
            } catch (error) {
                console.error('Error syncing data:', error);
            }
        }
        
        // Update status indicators
        this.updateOfflineStatus();
    }

    /**
     * Show a notification when connectivity status changes
     */
    showConnectivityNotification(isOnline) {
        const notificationContainer = document.getElementById('connectivity-notification');
        if (!notificationContainer) {
            // Create container if it doesn't exist
            const container = document.createElement('div');
            container.id = 'connectivity-notification';
            container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 300px;';
            document.body.appendChild(container);
        }
        
        const message = isOnline ? 
            'You are back online. Syncing your changes...' : 
            'You are offline. Changes will be saved locally and synced when you reconnect.';
            
        const bgColor = isOnline ? 'bg-success' : 'bg-warning';
        const icon = isOnline ? 'fa-wifi' : 'fa-wifi-slash';
        
        const notification = document.createElement('div');
        notification.className = `toast ${bgColor} text-white`;
        notification.setAttribute('role', 'alert');
        notification.setAttribute('aria-live', 'assertive');
        notification.setAttribute('aria-atomic', 'true');
        notification.innerHTML = `
            <div class="toast-header ${bgColor} text-white">
                <i class="fas ${icon} me-2"></i>
                <strong class="me-auto">${isOnline ? 'Online' : 'Offline'}</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        `;
        
        const notificationList = document.getElementById('connectivity-notification');
        notificationList.appendChild(notification);
        
        // Initialize and show the toast
        const toast = new bootstrap.Toast(notification, { delay: 5000 });
        toast.show();
        
        // Remove after it's hidden
        notification.addEventListener('hidden.bs.toast', () => {
            notification.remove();
        });
    }

    /**
     * Update the UI to show offline status indicators
     */
    updateOfflineStatus() {
        // Update offline indicator in the UI
        const offlineIndicator = document.getElementById('offline-indicator');
        if (offlineIndicator) {
            if (this.isOnline) {
                offlineIndicator.classList.add('d-none');
            } else {
                offlineIndicator.classList.remove('d-none');
            }
        }
        
        // Update pending sync badge
        this.updatePendingSyncCount();
    }

    /**
     * Save a note to IndexedDB and queue for syncing if offline
     */
    async saveNote(note) {
        try {
            // Save to IndexedDB cache
            await this.saveToCache('cachedNotes', note);
            
            // If online, try to sync directly
            if (this.isOnline) {
                return await this.syncNote(note);
            } else {
                // If offline, add to pending changes
                const pendingChange = {
                    type: 'update',
                    data: note,
                    timestamp: new Date().toISOString()
                };
                await this.addPendingChange(pendingChange);
                
                // Update UI to show pending changes
                this.updatePendingSyncCount();
                
                return { success: true, message: 'Note saved offline and queued for sync', isOffline: true };
            }
        } catch (error) {
            console.error('Error saving note:', error);
            return { success: false, message: 'Failed to save note: ' + error.message };
        }
    }

    /**
     * Retrieve a note from cache or server
     */
    async getNote(noteId) {
        try {
            // Try to get from cache first
            const cachedNote = await this.getFromCache('cachedNotes', noteId);
            
            // If online, try to get from server and update cache
            if (this.isOnline) {
                try {
                    const response = await fetch(`/notes/${noteId}/json`);
                    if (response.ok) {
                        const serverNote = await response.json();
                        // Update the cache with fresh data
                        await this.saveToCache('cachedNotes', serverNote);
                        return { success: true, data: serverNote, source: 'server' };
                    }
                } catch (error) {
                    console.log('Error fetching from server, using cached version:', error);
                }
            }
            
            // Return cached version if available, otherwise return error
            if (cachedNote) {
                return { success: true, data: cachedNote, source: 'cache' };
            } else {
                return { success: false, message: 'Note not found in cache and server unreachable' };
            }
        } catch (error) {
            console.error('Error retrieving note:', error);
            return { success: false, message: 'Failed to retrieve note: ' + error.message };
        }
    }

    /**
     * Get all notes from cache
     */
    async getAllNotes() {
        try {
            const cachedNotes = await this.getAllFromCache('cachedNotes');
            return { success: true, data: cachedNotes };
        } catch (error) {
            console.error('Error getting all notes:', error);
            return { success: false, message: 'Failed to retrieve notes: ' + error.message };
        }
    }

    /**
     * Check sync status and update UI
     */
    async checkSyncStatus() {
        try {
            const pendingChanges = await this.getAllFromCache('pendingChanges');
            this.pendingSyncCount = pendingChanges.length;
            this.updatePendingSyncCount();
        } catch (error) {
            console.error('Error checking sync status:', error);
        }
    }

    /**
     * Update the UI to show pending sync count
     */
    updatePendingSyncCount() {
        const syncBadge = document.getElementById('sync-badge');
        if (syncBadge) {
            if (this.pendingSyncCount > 0) {
                syncBadge.textContent = this.pendingSyncCount;
                syncBadge.classList.remove('d-none');
            } else {
                syncBadge.classList.add('d-none');
            }
        }
    }

    /**
     * Synchronize all pending changes with the server
     */
    async syncData() {
        if (!this.isOnline) {
            console.log('Cannot sync data while offline');
            return { success: false, message: 'Cannot sync while offline' };
        }
        
        try {
            const pendingChanges = await this.getAllFromCache('pendingChanges');
            
            if (pendingChanges.length === 0) {
                console.log('No pending changes to sync');
                return { success: true, message: 'No changes to sync' };
            }
            
            console.log(`Syncing ${pendingChanges.length} pending changes`);
            
            // Sort by timestamp to process in order
            pendingChanges.sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));
            
            // Process each change
            for (const change of pendingChanges) {
                try {
                    switch (change.type) {
                        case 'create':
                        case 'update':
                            await this.syncNote(change.data);
                            break;
                        case 'delete':
                            await this.syncDeleteNote(change.data.id);
                            break;
                        default:
                            console.warn('Unknown change type:', change.type);
                    }
                    
                    // Remove from pending changes
                    await this.deleteFromCache('pendingChanges', change.id);
                } catch (error) {
                    console.error('Error syncing change:', error, change);
                    // Don't remove from pending changes so we can try again
                }
            }
            
            // Update UI
            await this.checkSyncStatus();
            
            return { success: true, message: 'Sync completed successfully' };
        } catch (error) {
            console.error('Error syncing data:', error);
            return { success: false, message: 'Failed to sync data: ' + error.message };
        }
    }

    /**
     * Sync a single note with the server
     */
    async syncNote(note) {
        if (!this.isOnline) {
            return { success: false, message: 'Cannot sync while offline' };
        }
        
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            
            const response = await fetch(`/notes/${note.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    title: note.title,
                    content: note.content
                })
            });
            
            if (response.ok) {
                const result = await response.json();
                // Update the cache with the server version
                await this.saveToCache('cachedNotes', result.note);
                return { success: true, data: result.note };
            } else {
                throw new Error(`Server returned ${response.status}: ${response.statusText}`);
            }
        } catch (error) {
            console.error('Error syncing note:', error);
            throw error;
        }
    }

    /**
     * Sync a note deletion with the server
     */
    async syncDeleteNote(noteId) {
        if (!this.isOnline) {
            return { success: false, message: 'Cannot sync while offline' };
        }
        
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            
            const response = await fetch(`/notes/${noteId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                // Remove from cache as well
                await this.deleteFromCache('cachedNotes', noteId);
                return { success: true };
            } else {
                throw new Error(`Server returned ${response.status}: ${response.statusText}`);
            }
        } catch (error) {
            console.error('Error syncing note deletion:', error);
            throw error;
        }
    }

    /**
     * Add a pending change to be synced later
     */
    async addPendingChange(change) {
        await this.addToCache('pendingChanges', change);
        this.pendingSyncCount++;
    }

    /**
     * Open the IndexedDB database
     */
    openDatabase() {
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
                resolve(db);
            };
        });
    }

    /**
     * Save an item to the cache
     */
    async saveToCache(storeName, item) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(storeName, 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.put(item);
            
            request.onsuccess = () => {
                resolve(request.result);
            };
            
            request.onerror = () => {
                reject(request.error);
            };
        });
    }

    /**
     * Add an item to the cache
     */
    async addToCache(storeName, item) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(storeName, 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.add(item);
            
            request.onsuccess = () => {
                resolve(request.result);
            };
            
            request.onerror = () => {
                reject(request.error);
            };
        });
    }

    /**
     * Get an item from the cache
     */
    async getFromCache(storeName, key) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(storeName, 'readonly');
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

    /**
     * Get all items from a store
     */
    async getAllFromCache(storeName) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(storeName, 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.getAll();
            
            request.onsuccess = () => {
                resolve(request.result);
            };
            
            request.onerror = () => {
                reject(request.error);
            };
        });
    }

    /**
     * Delete an item from the cache
     */
    async deleteFromCache(storeName, key) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(storeName, 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.delete(key);
            
            request.onsuccess = () => {
                resolve();
            };
            
            request.onerror = () => {
                reject(request.error);
            };
        });
    }
}

// Register service worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', async () => {
        try {
            const registration = await navigator.serviceWorker.register('/sw.js');
            console.log('ServiceWorker registration successful with scope:', registration.scope);
            
            // Request permission for notifications
            if ('Notification' in window) {
                Notification.requestPermission();
            }
        } catch (error) {
            console.error('ServiceWorker registration failed:', error);
        }
    });
}

// Initialize the offline manager
const offlineManager = new OfflineManager();

// Make it globally available
window.offlineManager = offlineManager; 