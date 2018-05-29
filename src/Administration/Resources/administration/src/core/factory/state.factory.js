/**
 * @module core/factory/state
 */
export default {
    registerStore,
    getStore,
    getStoreRegistry
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
 */
function registerStore(name, store) {
    if (!name || !name.length) {
        return;
    }

    storeRegistry.set(name, store);
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
