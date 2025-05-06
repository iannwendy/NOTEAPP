/**
 * Sync.js - Quản lý đồng bộ hóa dữ liệu giữa IndexedDB và server
 */
import { offlineDB } from './database';

class SyncManager {
  constructor() {
    this.isOnline = navigator.onLine;
    this.syncInProgress = false;
    this.syncQueue = [];
    this.syncListeners = [];
    this.offlineDB = offlineDB;
    
    // Load pending operations from IndexedDB
    this.loadPendingOperations();
    
    // Đăng ký các sự kiện mạng
    window.addEventListener('online', this.handleOnlineStatusChange.bind(this));
    window.addEventListener('offline', this.handleOnlineStatusChange.bind(this));
    
    // Đăng ký lắng nghe thông báo từ Service Worker
    if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
      navigator.serviceWorker.addEventListener('message', this.handleServiceWorkerMessage.bind(this));
    }
  }
  
  /**
   * Xử lý thay đổi trạng thái kết nối
   */
  handleOnlineStatusChange() {
    const wasOnline = this.isOnline;
    this.isOnline = navigator.onLine;
    
    console.log(`Kết nối mạng: ${this.isOnline ? 'Online' : 'Offline'}`);
    
    // Thông báo cho người dùng
    if (this.isOnline && !wasOnline) {
      this.notifyUser('Kết nối đã được khôi phục!', 'Dữ liệu của bạn sẽ được đồng bộ hóa ngay.');
      // Đồng bộ hóa dữ liệu
      this.syncAll();
    } else if (!this.isOnline && wasOnline) {
      this.notifyUser('Mất kết nối!', 'Ứng dụng sẽ chuyển sang chế độ offline. Các thay đổi của bạn sẽ được lưu cục bộ và đồng bộ hóa khi có kết nối internet trở lại.');
    }
    
    // Thông báo cho các trình lắng nghe
    this.notifyListeners({
      type: 'CONNECTION_CHANGE',
      isOnline: this.isOnline
    });
  }
  
  /**
   * Xử lý thông báo từ Service Worker
   * @param {MessageEvent} event 
   */
  handleServiceWorkerMessage(event) {
    const message = event.data;
    
    if (message.type === 'SYNC_STARTED') {
      this.syncInProgress = true;
      this.notifyListeners({
        type: 'SYNC_STATUS_CHANGE',
        inProgress: true
      });
    } else if (message.type === 'SYNC_COMPLETED') {
      this.syncInProgress = false;
      this.notifyListeners({
        type: 'SYNC_STATUS_CHANGE',
        inProgress: false
      });
    }
  }
  
  /**
   * Đăng ký một listener để nhận thông báo về đồng bộ hóa
   * @param {Function} callback 
   */
  addSyncListener(callback) {
    if (typeof callback === 'function') {
      this.syncListeners.push(callback);
    }
  }
  
  /**
   * Hủy đăng ký một listener
   * @param {Function} callback 
   */
  removeSyncListener(callback) {
    this.syncListeners = this.syncListeners.filter(listener => listener !== callback);
  }
  
  /**
   * Thông báo cho tất cả các listener
   * @param {Object} data 
   */
  notifyListeners(data) {
    this.syncListeners.forEach(listener => {
      try {
        listener(data);
      } catch (err) {
        console.error('Lỗi trong sync listener:', err);
      }
    });
  }
  
  /**
   * Hiển thị thông báo cho người dùng
   * @param {string} title 
   * @param {string} message 
   */
  notifyUser(title, message) {
    // Kiểm tra nếu có phần tử thông báo
    const notificationContainer = document.getElementById('offline-notification');
    
    if (notificationContainer) {
      const titleElement = notificationContainer.querySelector('.notification-title');
      const messageElement = notificationContainer.querySelector('.notification-message');
      
      if (titleElement) titleElement.textContent = title;
      if (messageElement) messageElement.textContent = message;
      
      notificationContainer.classList.add('visible');
      
      // Ẩn thông báo sau 5 giây
      setTimeout(() => {
        notificationContainer.classList.remove('visible');
      }, 5000);
    } else {
      // Tạo thông báo nếu chưa có
      const notification = document.createElement('div');
      notification.id = 'offline-notification';
      notification.className = 'offline-notification visible';
      
      notification.innerHTML = `
        <div class="notification-content">
          <div class="notification-title">${title}</div>
          <div class="notification-message">${message}</div>
        </div>
        <button class="notification-close">&times;</button>
      `;
      
      document.body.appendChild(notification);
      
      // Thêm sự kiện đóng thông báo
      const closeButton = notification.querySelector('.notification-close');
      if (closeButton) {
        closeButton.addEventListener('click', () => {
          notification.classList.remove('visible');
        });
      }
      
      // Ẩn thông báo sau 5 giây
      setTimeout(() => {
        notification.classList.remove('visible');
      }, 5000);
    }
  }
  
  /**
   * Thêm một thao tác vào hàng đợi đồng bộ hóa
   * @param {string} operation - Loại thao tác (create, update, delete)
   * @param {string} url - URL API
   * @param {Object} data - Dữ liệu gửi lên server
   * @param {Object} localData - Dữ liệu lưu cục bộ
   */
  addToSyncQueue(operation, url, data, localData) {
    const syncItem = {
      operation,
      url,
      data,
      localData
    };
    
    offlineDB.savePendingOperation(syncItem)
      .then(savedItem => {
        this.syncQueue.push(savedItem);
        console.log(`Đã thêm thao tác ${operation} vào hàng đợi đồng bộ hóa`, savedItem);
        
        // Thử đồng bộ nếu đang online
        if (this.isOnline && !this.syncInProgress) {
          this.processQueue();
        }
      })
      .catch(err => {
        console.error('Lỗi khi thêm thao tác vào hàng đợi:', err);
      });
  }
  
  /**
   * Xử lý hàng đợi đồng bộ hóa
   */
  processQueue() {
    if (!this.isOnline || this.syncInProgress || this.syncQueue.length === 0) {
      return;
    }
    
    this.syncInProgress = true;
    this.notifyListeners({
      type: 'SYNC_STATUS_CHANGE',
      inProgress: true,
      pendingCount: this.syncQueue.length
    });
    
    const processNext = () => {
      if (this.syncQueue.length === 0) {
        this.syncInProgress = false;
        this.notifyListeners({
          type: 'SYNC_STATUS_CHANGE',
          inProgress: false,
          pendingCount: 0
        });
        return;
      }
      
      const item = this.syncQueue.shift();
      this.processSyncItem(item)
        .then(() => {
          // Xóa thao tác khỏi IndexedDB
          return offlineDB.deleteOperation(item.id);
        })
        .catch(err => {
          console.error('Lỗi khi đồng bộ hóa:', err);
          // Đánh dấu thao tác là thất bại
          return offlineDB.updateOperationStatus(item.id, 'failed');
        })
        .finally(() => {
          // Xử lý thao tác tiếp theo
          processNext();
        });
    };
    
    processNext();
  }
  
  /**
   * Xử lý một mục đồng bộ hóa
   * @param {Object} item 
   * @returns {Promise}
   */
  processSyncItem(item) {
    const { operation, url, data } = item;
    let method = 'GET';
    
    switch (operation) {
      case 'create':
        method = 'POST';
        break;
      case 'update':
        method = 'PUT';
        break;
      case 'delete':
        method = 'DELETE';
        break;
    }
    
    return fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'Accept': 'application/json'
      },
      credentials: 'same-origin',
      body: data ? JSON.stringify(data) : undefined
    })
    .then(response => {
      if (!response.ok) {
        throw new Error(`Server responded with ${response.status}: ${response.statusText}`);
      }
      return response.json();
    })
    .then(responseData => {
      console.log(`Đồng bộ hóa thành công: ${operation}`, responseData);
      return responseData;
    });
  }
  
  /**
   * Đồng bộ hóa tất cả dữ liệu đang chờ
   */
  syncAll() {
    if (!this.isOnline) {
      console.log('Không thể đồng bộ hóa khi offline');
      return Promise.reject(new Error('Không thể đồng bộ hóa khi offline'));
    }
    
    return offlineDB.getPendingOperations()
      .then(operations => {
        if (operations.length === 0) {
          console.log('Không có thao tác nào đang chờ đồng bộ hóa');
          return;
        }
        
        console.log(`Đang đồng bộ hóa ${operations.length} thao tác`);
        this.syncQueue = operations;
        
        // Đăng ký background sync nếu trình duyệt hỗ trợ
        if ('serviceWorker' in navigator && 'SyncManager' in window) {
          navigator.serviceWorker.ready
            .then(registration => {
              return registration.sync.register('sync-notes');
            })
            .catch(err => {
              console.error('Lỗi khi đăng ký background sync:', err);
              // Nếu không thể đăng ký background sync, xử lý thủ công
              this.processQueue();
            });
        } else {
          // Nếu không hỗ trợ background sync, xử lý thủ công
          this.processQueue();
        }
      });
  }
  
  /**
   * Lưu một ghi chú cục bộ và thêm vào hàng đợi đồng bộ hóa nếu offline
   * @param {Object} note - Ghi chú cần lưu
   * @param {boolean} isNew - Có phải ghi chú mới không
   * @returns {Promise}
   */
  saveNote(note, isNew = false) {
    // Đảm bảo note có updated_at và format đúng
    if (!note.updated_at) {
      note.updated_at = offlineDB.getCurrentTimestamp();
    }
    
    // Lưu ghi chú vào IndexedDB
    return offlineDB.saveNote(note)
      .then(savedNote => {
        // Nếu online, gửi lên server ngay
        if (this.isOnline) {
          const url = isNew ? '/notes' : `/notes/${note.id}`;
          const method = isNew ? 'POST' : 'PUT';
          
          return fetch(url, {
            method,
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
              'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify(note)
          })
          .then(response => {
            if (!response.ok) {
              throw new Error(`Server responded with ${response.status}`);
            }
            return response.json();
          })
          .then(serverNote => {
            // Cập nhật lại note trong IndexedDB nếu có thay đổi từ server
            if (JSON.stringify(serverNote) !== JSON.stringify(note)) {
              return offlineDB.saveNote(serverNote);
            }
            return serverNote;
          })
          .catch(err => {
            console.error('Lỗi khi lưu ghi chú lên server:', err);
            // Đưa vào hàng đợi đồng bộ hóa nếu gặp lỗi
            this.addToSyncQueue(
              isNew ? 'create' : 'update',
              url,
              note,
              note
            );
            return savedNote;
          });
        } else {
          // Nếu offline, thêm vào hàng đợi đồng bộ hóa
          this.addToSyncQueue(
            isNew ? 'create' : 'update',
            isNew ? '/notes' : `/notes/${note.id}`,
            note,
            note
          );
          return savedNote;
        }
      });
  }
  
  /**
   * Xóa một ghi chú
   * @param {number} noteId - ID của ghi chú cần xóa
   * @returns {Promise}
   */
  deleteNote(noteId) {
    // Xóa ghi chú khỏi IndexedDB
    return offlineDB.deleteNote(noteId)
      .then(() => {
        // Nếu online, gửi lên server ngay
        if (this.isOnline) {
          return fetch(`/notes/${noteId}`, {
            method: 'DELETE',
            headers: {
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
              'Accept': 'application/json'
            },
            credentials: 'same-origin'
          })
          .then(response => {
            if (!response.ok) {
              throw new Error(`Server responded with ${response.status}`);
            }
            return response.json();
          })
          .catch(err => {
            console.error('Lỗi khi xóa ghi chú trên server:', err);
            // Đưa vào hàng đợi đồng bộ hóa nếu gặp lỗi
            this.addToSyncQueue(
              'delete',
              `/notes/${noteId}`,
              null,
              { id: noteId }
            );
          });
        } else {
          // Nếu offline, thêm vào hàng đợi đồng bộ hóa
          this.addToSyncQueue(
            'delete',
            `/notes/${noteId}`,
            null,
            { id: noteId }
          );
        }
      });
  }
  
  /**
   * Tải và lưu trữ ghi chú cục bộ
   * @param {number} userId - ID của người dùng
   * @returns {Promise}
   */
  loadAndCacheUserNotes(userId) {
    // Chỉ tải từ server nếu online
    if (this.isOnline) {
      return fetch('/notes?format=json', {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      })
      .then(response => {
        if (!response.ok) {
          throw new Error(`Server responded with ${response.status}`);
        }
        return response.json();
      })
      .then(notes => {
        // Lưu tất cả ghi chú vào IndexedDB
        return offlineDB.saveNotes(notes);
      })
      .catch(err => {
        console.error('Lỗi khi tải ghi chú từ server:', err);
        // Nếu lỗi, trả về ghi chú đã lưu cục bộ
        return offlineDB.getNotesByUser(userId);
      });
    } else {
      // Nếu offline, lấy từ IndexedDB
      return offlineDB.getNotesByUser(userId);
    }
  }
  
  /**
   * Kiểm tra và tải ghi chú cụ thể
   * @param {number} noteId - ID của ghi chú
   * @returns {Promise}
   */
  loadAndCacheNote(noteId) {
    // Thử lấy từ IndexedDB trước
    return offlineDB.getNote(noteId)
      .then(localNote => {
        // Nếu đang online và có kết nối, làm mới từ server
        if (this.isOnline) {
          return fetch(`/notes/${noteId}/json`, {
            method: 'GET',
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
          })
          .then(response => {
            if (!response.ok) {
              throw new Error(`Server responded with ${response.status}`);
            }
            return response.json();
          })
          .then(serverNote => {
            // Lưu vào IndexedDB để sử dụng offline
            return offlineDB.saveNote(serverNote).then(() => serverNote);
          })
          .catch(err => {
            console.error(`Lỗi khi tải ghi chú #${noteId} từ server:`, err);
            return localNote; // Trả về phiên bản cục bộ nếu có lỗi
          });
        }
        return localNote;
      });
  }
  
  /**
   * Load pending operations from IndexedDB
   */
  loadPendingOperations() {
    offlineDB.getAllPendingOperations()
      .then(operations => {
        if (operations && operations.length > 0) {
          this.syncQueue = operations;
          console.log(`Loaded ${operations.length} pending operations from IndexedDB`);
          
          // Attempt to process the queue if online
          if (this.isOnline && !this.syncInProgress) {
            this.processQueue();
          }
        }
      })
      .catch(err => {
        console.error('Error loading pending operations:', err);
      });
  }
}

// Export singleton instance
export const syncManager = new SyncManager(); 