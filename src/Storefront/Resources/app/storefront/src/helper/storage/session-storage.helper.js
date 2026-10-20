import MemoryStorage from 'src/helper/storage/memory-storage.helper';

/**
 * Storage that is scoped to the current browsing session and therefore
 * does not outlive the browser tab it was written in.
 *
 * @sw-package framework
 */
class SessionStorageSingleton {

    constructor() {
        this._storage = SessionStorageSingleton._isSupported()
            ? window.sessionStorage
            : new MemoryStorage();
    }

    /**
     * returns if the session storage is supported
     *
     * @returns {boolean}
     * @private
     */
    static _isSupported() {
        try {
            const testKey = '__session_storage_test';
            window.sessionStorage.setItem(testKey, '1');
            window.sessionStorage.removeItem(testKey);

            return true;
        } catch (e) {
            return false;
        }
    }

    /**
     * returns the currently used storage
     *
     * @returns {Storage|MemoryStorage}
     */
    getStorage() {
        return this._storage;
    }
}

/**
 * Create the SessionStorage instance.
 * @type {Readonly<SessionStorageSingleton>}
 */
export const SessionStorageInstance = Object.freeze(new SessionStorageSingleton());

export default SessionStorageInstance.getStorage();
