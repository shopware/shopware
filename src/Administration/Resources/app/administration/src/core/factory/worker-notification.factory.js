/**
 * @module core/factory/worker-notification
 */
import MiddlewareHelper from 'src/core/helper/middleware.helper';
import { hasOwnProperty } from 'src/core/service/utils/object.utils';
import types from 'src/core/service/utils/types.utils';

export default {
    getRegistry,
    register,
    remove,
    override,
    initialize,
    resetHelper,
};

/**
 * Registry which holds all worker notification middleware functions
 * @type {Map}
 */
const registry = new Map();

/**
 * Middleware helper instance
 * @type {MiddlewareHelper}
 */
let helper = new MiddlewareHelper();

/**
 * Indicates if the middleware helper is initialized
 * @type {boolean}
 */
let initialized = false;

/**
 * Returns the registry
 * @return {Map}
 */
function getRegistry() {
    return registry;
}

/**
 * Registers a new worker notification middleware function.
 * @param {String} name
 * @param {Object} opts
 * @return {boolean}
 */
function register(name, opts) {
    if (!name || !name.length) {
        return false;
    }

    if (registry.has(name)) {
        return false;
    }

    if (!validateOpts(opts)) {
        return false;
    }

    registry.set(name, opts);
    return true;
}

/**
 * Removes an existing worker notification middleware function.
 * @param {String} name
 * @return {boolean}
 */
function remove(name) {
    if (!name || !name.length) {
        return false;
    }

    if (!registry.has(name)) {
        return false;
    }

    registry.delete(name);
    return true;
}

/**
 * Overrides an existing worker notification middleware function.
 * @param {String} name
 * @param {Object} opts
 * @return {boolean}
 */
function override(name, opts) {
    if (!registry.has(name)) {
        return false;
    }

    if (!validateOpts(opts)) {
        return false;
    }

    registry.set(name, opts);
    return true;
}

/**
 * Initializes the middleware helper. If the helper was initialized before, the instance of the helper will be returned.
 * @return {MiddlewareHelper}
 */
function initialize() {
    if (initialized) {
        return helper;
    }

    initialized = true;
    getRegistry().forEach(({ fn, name }) => {
        helper.use(middlewareFunctionWrapper(name, fn));
    });
    return helper;
}

/**
 * Helper method which wraps the middleware function.
 * @param {String} name
 * @param {Function} fn
 * @return {Function}
 */
function middlewareFunctionWrapper(name, fn) {
    return (next, data) => {
        const entry = data.queue.find(
            (q) => q.name === name,
        ) || null;
        const mergedData = { ...data, ...{ entry, name } };

        if (entry === null) {
            next();
        } else {
            fn.call(null, next, mergedData);
        }
    };
}

/**
 * Validates the options object
 * @param {Object} opts
 * @return {Boolean|boolean}
 */
function validateOpts(opts) {
    return (hasOwnProperty(opts, 'name')
        && opts.name.length > 0
        && hasOwnProperty(opts, 'fn')
        && types.isFunction(opts.fn));
}

/**
 * Helper method for unit tests
 * @return {boolean}
 */
function resetHelper() {
    helper = new MiddlewareHelper();
    initialized = false;
    return true;
}
