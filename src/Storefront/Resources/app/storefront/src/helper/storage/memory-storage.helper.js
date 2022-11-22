/**
 * this class is mainly a fallback
 * if the session, local or cookie storage fails.
 *
 * @package storefront
 */
export default class MemoryStorage {
    constructor() {
        this._storage = {};
    }

    /**
     * @param {string} key
     * @param {*} value
     *
     * @returns {*}
     */
    setItem(key, value) {
        return this._storage[key] = value;
    }

    /**
     * @param {string} key
     *
     * @returns {*}
     */
    getItem(key) {
        return Object.prototype.hasOwnProperty.call(this._storage, key) ? this._storage[key] : null;
    }

    /**
     * @param {string} key
     *
     * @returns {boolean}
     */
    removeItem(key) {
        return delete this._storage[key];
    }

    /**
     * @param {number} index
     *
     * @returns {any}
     */
    key(index) {
        return Object.values(this._storage)[index] || null;
    }

    /**
     * @returns {{}}
     */
    clear() {
        return this._storage = {};
    }
}
