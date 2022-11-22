/**
 * Debouncer
 * @package storefront
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
            if (immediate &&  !timeout) {
                setTimeout(callback.bind(callback, ...args), 0);
            }

            clearTimeout(timeout);
            timeout = setTimeout(callback.bind(callback, ...args), delay);
        };
    }
}
