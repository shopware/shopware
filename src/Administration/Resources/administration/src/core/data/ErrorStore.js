/**
 * @module core/data/ErrorStore
 */
import ShopwareError from 'src/core/data/ShopwareError';

class ErrorStore {
    constructor() {
        this.errors = {
            system: {},
            api: {},
            validation: {}
        };
    }

    /**
     * Translates an error from the api to a ShopwareError object
     * @param {Object} apiError
     * @param {string} root
     * @return {ShopwareError}
     */
    static transformApiError(apiError, root) {
        let propertyPath = '';
        const pathElements = apiError.source.pointer.split('/');
        pathElements.shift();

        if (root) {
            propertyPath = `${root}.`;
        }

        propertyPath = `${propertyPath}${pathElements.join('.')}`;

        return { propertyPath, shopwareError: new ShopwareError(apiError) };
    }

    /**
     * Registers the data binding of a field component to automatically match new errors.
     *
     * @param {String} expression
     * @return {ShopwareError}
     */
    registerFormField(expression) {
        return ErrorStore.createAtPath(expression, this.errors.api);
    }

    /**
     * Add a new error to the store.
     *
     * @param {string} expression
     * @param {ShopwareError} error
     * @param {string} type
     * @return {boolean}
     */
    setErrorData() {
        console.warn('setErrorData must be overiden by your concrete implementation of the error store');
    }

    /**
     * Sets an error back to default state.
     *
     * @param {string} expression
     * @param {string} type
     * @return {boolean}
     */
    resetError(expression, type) {
        return ErrorStore.createAtPath(expression, this.errors[type]);
    }

    /**
     * Remove an error from the store.
     *
     * @param {string} expression
     * @param {string} type
     * @return {boolean}
     */
    deleteError() {
        console.warn('deleteError must be overiden by your concrete implementation of the error store');
    }

    /**
     * Returns the error of a store or null if it does not exist
     *
     * @param expression
     * @param store
     * @returns {ShopwareError | null}
     * @protected
     */
    static getFromPath(expression, store) {
        const path = expression.split('.');
        return path.reduce(ErrorStore.resolvePath, store);
    }

    /**
     * @param {string} expression
     * @returns {{path: array, errorName: string}}
     * @protected
     */
    static getPathAndName(expression) {
        const path = expression.split('.');
        const errorName = path.pop();

        return { path, errorName };
    }

    /**
     * @param expression
     * @param store
     * @returns { { container: Object | null, errorName: String }}
     * @protected
     */
    static getErrorContainer(expression, store) {
        const { path, errorName } = ErrorStore.getPathAndName(expression);

        const container = path.reduce(ErrorStore.resolvePath, store);
        return { container, path, errorName };
    }

    /**
     * Return a new ShopwareError in a given store
     *
     * @param {string} expression
     * @param {Object} store
     * @param {function} setReactive
     * @returns {ShopwareError}
     * @protected
     */
    static createAtPath(expression, store, setReactive = Object.defineProperty) {
        const { path, errorName } = ErrorStore.getPathAndName(expression);

        const endpoint = path.reduce((currentPointer, nextPath) => {
            if (ErrorStore.isUdef(currentPointer[nextPath])) {
                setReactive(currentPointer, nextPath, {});
            }

            return currentPointer[nextPath];
        }, store);

        setReactive(endpoint, errorName, new ShopwareError());
        return endpoint[errorName];
    }

    /**
     *
     * @param {string} expression
     * @param {Object} store
     * @param {function} deleteReactive
     * @protected
     */
    static deleteAtPath(expression, store, deleteReactive = null) {
        const { container, path, errorName } = ErrorStore.getErrorContainer(expression, store);

        // already deleted
        if (container === null || !container.hasOwnProperty(errorName)) {
            return false;
        }

        if (typeof deleteReactive === 'function') {
            deleteReactive(container, errorName);
        } else {
            delete container[errorName];
        }

        if (Object.keys(container).length > 0) {
            return true;
        }

        return ErrorStore.deleteAtPath(path.join('.'), store, deleteReactive);
    }

    /**
     * @param pointer
     * @returns {boolean}
     * @private
     */
    static isUdef(pointer) {
        return pointer === null || pointer === undefined;
    }

    /**
     * @param currentPointer
     * @param next
     * @returns {null}
     * @private
     */
    static resolvePath(currentPointer, next) {
        if (ErrorStore.isUdef(currentPointer)) {
            return null;
        }

        return currentPointer.hasOwnProperty(next) ? currentPointer[next] : null;
    }
}

export default ErrorStore;
