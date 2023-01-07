/**
 * @package admin
 *
 * @module core/factory/state
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    registerStore,
    getStore,
    getStoreRegistry,
};

/**
 * Registry for the state stores.
 *
 * @type {Map<any, any>}
 */
const storeRegistry = new Map();

/**
 * Register a new state storage.
 *
 * @param name
 * @param store
 * @return {any}
 */
function registerStore(name, store) {
    if (!name || !name.length) {
        return null;
    }

    storeRegistry.set(name, store);

    return getStore(name);
}

/**
 * Get a store by name.
 *
 * @param name
 * @return {any}
 */
function getStore(name) {
    return storeRegistry.get(name);
}

/**
 * Get the complete store registry.
 *
 * @return {Map<any, any>}
 */
function getStoreRegistry() {
    return storeRegistry;
}
