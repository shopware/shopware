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
        let index = 0;
        const next = () => {
            if (index >= this.stack.length) {
                return;
            }

            const layer = this.stack[index];
            index += 1;

            layer.apply(null, [next, ...args]);
        };
        next();
    }
}
