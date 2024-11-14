import CookieStorage from 'src/helper/storage/cookie-storage.helper';
import MemoryStorage from 'src/helper/storage/memory-storage.helper';

/**
 * @package storefront
 */
class StorageSingleton {

    constructor() {
        this._storage = null;
        this._chooseStorage();
        this._validateStorage();
    }

    /**
     * sets the best available storage
     *
     * @returns {*}
     * @private
     */
    _chooseStorage() {
        if (StorageSingleton._isSupported('localStorage')) {
            return this._storage = window.localStorage;
        }

        if (StorageSingleton._isSupported('sessionStorage')) {
            return this._storage = window.sessionStorage;
        }

        if (CookieStorage.isSupported()) {
            return this._storage = CookieStorage;
        }

        return this._storage = new MemoryStorage();
    }

    /**
     * returns if the passed storage is supported
     *
     * @param storage
     * @returns {boolean}
     * @private
     */
    static _isSupported(storage) {
        try {
            const testKey = '__storage_test';
            window[storage].setItem(testKey, '1');
            window[storage].removeItem(testKey);

            return true;
        } catch (e) {
            return false;
        }
    }

    /**
     * ensures the same interface
     * for each storage
     *
     * @private
     */
    _validateStorage() {
        if (typeof this._storage.setItem !== 'function') {
            throw new Error('The storage must have a "setItem" function');
        }
        if (typeof this._storage.getItem !== 'function') {
            throw new Error('The storage must have a "getItem" function');
        }
        if (typeof this._storage.removeItem !== 'function') {
            throw new Error('The storage must have a "removeItem" function');
        }
        if (typeof this._storage.key !== 'function') {
            throw new Error('The storage must have a "key" function');
        }
        if (typeof this._storage.clear !== 'function') {
            throw new Error('The storage must have a "clear" function');
        }
    }

    /**
     * returns the currently used storage
     *
     * @returns {Storage|null}
     */
    getStorage() {
        return this._storage;
    }
}

/**
 * Create the Storage instance.
 * @type {Readonly<StorageSingleton>}
 */
export const StorageInstance = Object.freeze(new StorageSingleton());

export default StorageInstance.getStorage();
