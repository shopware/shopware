/**
 * @package admin
 *
 * @module core/factory/directive
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    addBootPromise,
    getBootPromises,
};

/**
 * Registry which holds all registered plugin promises.
 *
 * @type {Array}
 */
const pluginPromises = [];

/**
 * Add a new plugin promise.
 *
 * @returns {Object}
 */
function addBootPromise() {
    let promiseResolve;

    pluginPromises.push(new Promise((resolve) => {
        promiseResolve = resolve;
    }));

    return promiseResolve;
}

/**
 * Get all plugin promises.
 *
 * @returns {Array}
 */
function getBootPromises() {
    return pluginPromises;
}
