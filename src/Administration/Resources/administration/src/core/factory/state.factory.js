/**
 * @module core/factory/state
 */
import { warn } from 'src/core/service/utils/debug.utils';

export default {
    registerStateModule,
    getStateModule,
    getStateRegistry
};

/**
 * Registry for the state modules
 *
 * @type {Map<String, Object>}
 */
const stateRegistry = new Map();

/**
 * Register a new state module
 *
 * @param {String} name
 * @param {Object} [module={}]
 * @returns {boolean}
 */
function registerStateModule(name, module = {}) {
    if (!name || !name.length) {
        warn(
            'SateFactory',
            'A state module always needs a name.',
            module
        );
        return false;
    }

    if (stateRegistry.has(name)) {
        warn(
            'SateFactory',
            `A state module with the name ${name} already exists.`,
            module
        );
        return false;
    }

    stateRegistry.set(name, module);

    return true;
}

/**
 * Get a state module by name.
 *
 * @param {String} name
 * @returns {Object}
 */
function getStateModule(name) {
    return stateRegistry.get(name);
}

/**
 * Get the complete state registry.
 *
 * @returns {Map<String, Object>}
 */
function getStateRegistry() {
    return stateRegistry;
}
