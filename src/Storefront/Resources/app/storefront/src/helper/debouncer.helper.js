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
     * @returns {Function} Debounced callback with `cancel()` to drop its pending invocations and
     *                     `flush(...args)` to drop them and call back right away.
     */
    static debounce(callback, delay, immediate = false) {
        let timeout;
        let leading;

        const debounced = (...args) => {
            if (immediate &&  !timeout) {
                leading = setTimeout(callback.bind(callback, ...args), 0);
            }

            clearTimeout(timeout);
            timeout = setTimeout(callback.bind(callback, ...args), delay);
        };

        debounced.cancel = () => {
            clearTimeout(leading);
            clearTimeout(timeout);
            timeout = undefined;
        };

        debounced.flush = (...args) => {
            debounced.cancel();
            callback(...args);
        };

        return debounced;
    }
}
