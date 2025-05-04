/**
 * Database.js - Quản lý lưu trữ offline với IndexedDB
 */

class OfflineDB {
  constructor() {
    this.DB_NAME = 'notes-offline-db';
    this.DB_VERSION = 1;
    this.NOTES_STORE = 'notes';
    this.PENDING_STORE = 'pending-operations';
    this.db = null;
    
    // Khởi tạo database
    this.init();
  }
  
  /**
   * Khởi tạo IndexedDB
   * @returns {Promise} Promise khi database được mở thành công
   */
  init() {
    return new Promise((resolve, reject) => {
      if (this.db) {
        resolve(this.db);
        return;
      }
      
      // Kiểm tra sự hỗ trợ của trình duyệt
      if (!window.indexedDB) {
        reject(new Error('Trình duyệt của bạn không hỗ trợ IndexedDB.'));
        return;
      }
      
      const request = window.indexedDB.open(this.DB_NAME, this.DB_VERSION);
      
      // Xử lý khi cần tạo hoặc nâng cấp database
      request.onupgradeneeded = (event) => {
        const db = event.target.result;
        
        // Tạo objectStore cho notes nếu chưa tồn tại
        if (!db.objectStoreNames.contains(this.NOTES_STORE)) {
          const notesStore = db.createObjectStore(this.NOTES_STORE, { keyPath: 'id' });
          notesStore.createIndex('user_id', 'user_id', { unique: false });
          notesStore.createIndex('updated_at', 'updated_at', { unique: false });
        }
        
        // Tạo objectStore cho các thao tác đang chờ xử lý
        if (!db.objectStoreNames.contains(this.PENDING_STORE)) {
          const pendingStore = db.createObjectStore(this.PENDING_STORE, { 
            keyPath: 'id', 
            autoIncrement: true 
          });
          pendingStore.createIndex('timestamp', 'timestamp', { unique: false });
          pendingStore.createIndex('operation', 'operation', { unique: false });
          pendingStore.createIndex('syncStatus', 'syncStatus', { unique: false });
        }
      };
      
      request.onsuccess = (event) => {
        this.db = event.target.result;
        console.log('IndexedDB đã được mở thành công');
        resolve(this.db);
      };
      
      request.onerror = (event) => {
        console.error('Lỗi khi mở IndexedDB:', event.target.error);
        reject(event.target.error);
      };
    });
  }
  
  /**
   * Lưu trữ một ghi chú vào IndexedDB
   * @param {Object} note - Đối tượng ghi chú cần lưu
   * @returns {Promise}
   */
  saveNote(note) {
    return this.init()
      .then(db => {
        return new Promise((resolve, reject) => {
          const transaction = db.transaction([this.NOTES_STORE], 'readwrite');
          const store = transaction.objectStore(this.NOTES_STORE);
          
          // Đảm bảo note có updated_at
          if (!note.updated_at) {
            note.updated_at = new Date().toISOString();
          }
          
          const request = store.put(note); // Sử dụng put để cập nhật nếu đã tồn tại
          
          request.onsuccess = () => resolve(note);
          
          request.onerror = (event) => {
            console.error('Lỗi khi lưu ghi chú:', event.target.error);
            reject(event.target.error);
          };
          
          transaction.oncomplete = () => {
            console.log('Ghi chú đã được lưu trong IndexedDB:', note.id);
          };
        });
      });
  }
  
  /**
   * Lưu nhiều ghi chú cùng lúc
   * @param {Array} notes - Mảng các ghi chú cần lưu
   * @returns {Promise}
   */
  saveNotes(notes) {
    return this.init()
      .then(db => {
        return new Promise((resolve, reject) => {
          const transaction = db.transaction([this.NOTES_STORE], 'readwrite');
          const store = transaction.objectStore(this.NOTES_STORE);
          
          notes.forEach(note => {
            if (!note.updated_at) {
              note.updated_at = new Date().toISOString();
            }
            store.put(note);
          });
          
          transaction.oncomplete = () => {
            console.log(`${notes.length} ghi chú đã được lưu trong IndexedDB`);
            resolve(notes);
          };
          
          transaction.onerror = (event) => {
            console.error('Lỗi khi lưu nhiều ghi chú:', event.target.error);
            reject(event.target.error);
          };
        });
      });
  }
  
  /**
   * Lấy một ghi chú từ IndexedDB
   * @param {string|number} id - ID của ghi chú cần lấy
   * @returns {Promise<Object>}
   */
  getNote(id) {
    return this.init()
      .then(db => {
        return new Promise((resolve, reject) => {
          const transaction = db.transaction([this.NOTES_STORE], 'readonly');
          const store = transaction.objectStore(this.NOTES_STORE);
          const request = store.get(id);
          
          request.onsuccess = (event) => {
            resolve(event.target.result);
          };
          
          request.onerror = (event) => {
            console.error('Lỗi khi lấy ghi chú:', event.target.error);
            reject(event.target.error);
          };
        });
      });
  }
  
  /**
   * Lấy tất cả ghi chú của người dùng
   * @param {string|number} userId - ID của người dùng
   * @returns {Promise<Array>}
   */
  getNotesByUser(userId) {
    return this.init()
      .then(db => {
        return new Promise((resolve, reject) => {
          const transaction = db.transaction([this.NOTES_STORE], 'readonly');
          const store = transaction.objectStore(this.NOTES_STORE);
          const index = store.index('user_id');
          const request = index.getAll(userId);
          
          request.onsuccess = (event) => {
            resolve(event.target.result);
          };
          
          request.onerror = (event) => {
            console.error('Lỗi khi lấy ghi chú của người dùng:', event.target.error);
            reject(event.target.error);
          };
        });
      });
  }
  
  /**
   * Xóa một ghi chú khỏi IndexedDB
   * @param {string|number} id - ID của ghi chú cần xóa
   * @returns {Promise}
   */
  deleteNote(id) {
    return this.init()
      .then(db => {
        return new Promise((resolve, reject) => {
          const transaction = db.transaction([this.NOTES_STORE], 'readwrite');
          const store = transaction.objectStore(this.NOTES_STORE);
          const request = store.delete(id);
          
          request.onsuccess = () => {
            console.log(`Ghi chú ${id} đã được xóa khỏi IndexedDB`);
            resolve(true);
          };
          
          request.onerror = (event) => {
            console.error('Lỗi khi xóa ghi chú:', event.target.error);
            reject(event.target.error);
          };
        });
      });
  }
  
  /**
   * Lưu một thao tác đang chờ xử lý
   * @param {Object} operation - Thông tin về thao tác
   * @returns {Promise}
   */
  savePendingOperation(operation) {
    return this.init()
      .then(db => {
        return new Promise((resolve, reject) => {
          const transaction = db.transaction([this.PENDING_STORE], 'readwrite');
          const store = transaction.objectStore(this.PENDING_STORE);
          
          // Thêm timestamp cho thao tác
          operation.timestamp = Date.now();
          operation.syncStatus = 'pending';
          
          const request = store.add(operation);
          
          request.onsuccess = (event) => {
            const id = event.target.result;
            console.log(`Thao tác đã được lưu với ID: ${id}`);
            resolve({...operation, id});
          };
          
          request.onerror = (event) => {
            console.error('Lỗi khi lưu thao tác đang chờ:', event.target.error);
            reject(event.target.error);
          };
        });
      });
  }
  
  /**
   * Lấy tất cả các thao tác đang chờ đồng bộ hóa
   * @returns {Promise<Array>}
   */
  getPendingOperations() {
    return this.init()
      .then(db => {
        return new Promise((resolve, reject) => {
          const transaction = db.transaction([this.PENDING_STORE], 'readonly');
          const store = transaction.objectStore(this.PENDING_STORE);
          const index = store.index('syncStatus');
          const request = index.getAll('pending');
          
          request.onsuccess = (event) => {
            resolve(event.target.result);
          };
          
          request.onerror = (event) => {
            console.error('Lỗi khi lấy thao tác đang chờ:', event.target.error);
            reject(event.target.error);
          };
        });
      });
  }
  
  /**
   * Cập nhật trạng thái của thao tác
   * @param {number} id - ID của thao tác
   * @param {string} status - Trạng thái mới (completed, failed)
   * @returns {Promise}
   */
  updateOperationStatus(id, status) {
    return this.init()
      .then(db => {
        return new Promise((resolve, reject) => {
          const transaction = db.transaction([this.PENDING_STORE], 'readwrite');
          const store = transaction.objectStore(this.PENDING_STORE);
          
          const getRequest = store.get(id);
          
          getRequest.onsuccess = (event) => {
            const operation = event.target.result;
            if (operation) {
              operation.syncStatus = status;
              operation.syncedAt = new Date().toISOString();
              
              const updateRequest = store.put(operation);
              
              updateRequest.onsuccess = () => {
                resolve(operation);
              };
              
              updateRequest.onerror = (event) => {
                reject(event.target.error);
              };
            } else {
              reject(new Error(`Không tìm thấy thao tác với ID: ${id}`));
            }
          };
          
          getRequest.onerror = (event) => {
            reject(event.target.error);
          };
        });
      });
  }
  
  /**
   * Xóa thao tác đã hoàn thành khỏi danh sách chờ
   * @param {number} id - ID của thao tác cần xóa
   * @returns {Promise}
   */
  deleteOperation(id) {
    return this.init()
      .then(db => {
        return new Promise((resolve, reject) => {
          const transaction = db.transaction([this.PENDING_STORE], 'readwrite');
          const store = transaction.objectStore(this.PENDING_STORE);
          const request = store.delete(id);
          
          request.onsuccess = () => {
            resolve(true);
          };
          
          request.onerror = (event) => {
            reject(event.target.error);
          };
        });
      });
  }
  
  /**
   * Xóa tất cả thao tác đã đồng bộ thành công
   * @returns {Promise<number>} Số lượng thao tác đã xóa
   */
  clearCompletedOperations() {
    return this.init()
      .then(db => {
        return new Promise((resolve, reject) => {
          const transaction = db.transaction([this.PENDING_STORE], 'readwrite');
          const store = transaction.objectStore(this.PENDING_STORE);
          const index = store.index('syncStatus');
          const request = index.openCursor('completed');
          let count = 0;
          
          request.onsuccess = (event) => {
            const cursor = event.target.result;
            if (cursor) {
              store.delete(cursor.primaryKey);
              count++;
              cursor.continue();
            } else {
              resolve(count);
            }
          };
          
          request.onerror = (event) => {
            reject(event.target.error);
          };
        });
      });
  }
}

// Export singleton instance
export const offlineDB = new OfflineDB(); 