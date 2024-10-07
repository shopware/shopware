/**
 * @package admin
 *
 * @module core/factory/worker-notification
 */
import MiddlewareHelper from 'src/core/helper/middleware.helper';
import { hasOwnProperty } from 'src/core/service/utils/object.utils';
import types from 'src/core/service/utils/types.utils';
import type { App } from 'vue';

/** @private */
export type NotificationConfig = {
    isLoading?: boolean;
    metadata: { size: number };
    variant: string;
    growl: boolean;
    title: string;
    message: string;
    uuid?: string;
};

/** @private */
export type NotificationService = {
    create: (config: NotificationConfig) => Promise<string>;
    update: (config: NotificationConfig) => Promise<void>;
};

/**
 * @private
 */
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

/** @private */
export type NotificationWorkerOptions = {
    name: string;
    fn: (
        next: (name?: string, opts?: NotificationWorkerOptions) => unknown,
        opts: {
            entry: { size: number };
            $root: App<Element>;
            notification: NotificationService;
        },
    ) => unknown;
};

/**
 * Registers a new worker notification middleware function.
 * @param {String} name
 * @param {Object} opts
 * @return {boolean}
 */
function register(name: string, opts: NotificationWorkerOptions) {
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
function remove(name: string) {
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
function override(name: string, opts: { name: string; fn: () => unknown }) {
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
    getRegistry().forEach(({ fn, name }: { name: string; fn: () => unknown }) => {
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
function middlewareFunctionWrapper(name: string, fn: (next: () => unknown, data: unknown) => unknown) {
    return (next: () => unknown, data: { queue: Array<{ name: string }> }) => {
        const entry = data.queue.find((q) => q.name === name) || null;
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
function validateOpts(opts: NotificationWorkerOptions) {
    return hasOwnProperty(opts, 'name') && opts.name.length > 0 && hasOwnProperty(opts, 'fn') && types.isFunction(opts.fn);
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
