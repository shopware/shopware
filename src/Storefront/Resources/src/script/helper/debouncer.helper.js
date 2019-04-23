/**
 * Debouncer
 */
export default class Debouncer {

    /**
     * Debounce any given function
     *
     * @param {Function} callback
     * @param {int} delay
     * @param {boolean} immediate
     *
     * @returns {Function}
     */
    static debounce(callback, delay, immediate = false) {
        let timeout;

        return (...args) => {
            clearTimeout(timeout);
            timeout = setTimeout(callback.bind(callback, ...args), delay);
            if (immediate && !timeout) callback.call(callback, ...args);
        };
    }
}
