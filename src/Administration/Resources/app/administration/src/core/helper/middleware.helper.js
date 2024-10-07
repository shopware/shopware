/**
 * @package admin
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class MiddlewareHelper {
    /**
     * @constructor
     */
    constructor() {
        this._stack = [];
    }

    /**
     * Returns the stack of registered middleware
     *
     * @returns {Array}
     */
    get stack() {
        return this._stack;
    }

    /**
     * Registers a new middleware to the stack
     *
     * @param {Function} middleware
     * @throws Will throw an error if the argument is not a function
     * @return {MiddlewareHelper}
     */
    use(middleware) {
        if (typeof middleware !== 'function') {
            throw new Error('Middleware must be a function.');
        }
        this._stack.push(middleware);
        return this;
    }

    /**
     * Runs all registered middleware from the stack
     *
     * @param {*} args
     */
    go(...args) {
        // @see NEXT-15358 change _recursive_ to iterative stack processing
        // keeping function signature to stay compatible to existing code
        this.stack.forEach((frame) => frame(() => {}, ...args));
    }
}
