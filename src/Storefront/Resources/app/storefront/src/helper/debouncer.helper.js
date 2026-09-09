/**
 * Debouncer
 * @sw-package framework
 */
export default class Debouncer {

    /**
     * Debounce any given function
     *
     * @param {Function} callback
     * @param {int} delay
     * @param {boolean} immediate
     *
     * @returns {Function} Debounced callback with a cancel() method for its delayed invocation.
     */
    static debounce(callback, delay, immediate = false) {
        let timeout;

        const debounced = (...args) => {
            if (immediate &&  !timeout) {
                setTimeout(callback.bind(callback, ...args), 0);
            }

            clearTimeout(timeout);
            timeout = setTimeout(callback.bind(callback, ...args), delay);
        };

        // Cancel the pending delayed invocation, allowing the next call to start afresh.
        debounced.cancel = () => {
            clearTimeout(timeout);
            timeout = undefined;
        };

        return debounced;
    }
}
