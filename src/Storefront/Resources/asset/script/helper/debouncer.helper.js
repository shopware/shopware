/**
 * Debouncer
 */
export default class Debouncer {

    /**
     * Debounce any given function
     * @param {Function} func
     * @param {int} wait
     * @param {boolean} immediate
     * @returns {Function}
     */
    static debounce(func, wait, immediate) {
        let timeout;

        return () => {
            const context = this, args = arguments;
            const later = () => {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }
}
