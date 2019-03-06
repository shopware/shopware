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
            let context = this, args = arguments;
            let later = () => {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            let callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }
}
