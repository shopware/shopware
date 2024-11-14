/**
 * @package admin
 *
 * @module core/factory/api-service
 */
import { warn } from 'src/core/service/utils/debug.utils';

/**
 * @private
 */
export default {
    getRegistry,
    register,
    getByName,
    getServices,
    has,
};

/**
 * Registry which holds all registered api services
 *
 * @type {Map}
 */
const apiServiceRegistry = new Map();

/**
 * Factory name
 * @type {string}
 */
const name = 'ApiServiceFactory';

/**
 * Get the complete apiService registry
 *
 * @returns {Map<String, Function>}
 */
function getRegistry() {
    return apiServiceRegistry;
}

/**
 * Register a new apiService
 *
 * @param {String} apiServiceName
 * @param {Function} [apiService=null]
 * @returns {boolean}
 */
function register(apiServiceName, apiService = null) {
    if (!apiServiceName || !apiServiceName.length) {
        warn(name, 'A apiService always needs a name');
        return false;
    }

    if (has(apiServiceName)) {
        warn(
            name,
            `The apiService "${apiServiceName}" is already registered. Please select a unique name for your apiService.`,
        );
        return false;
    }

    apiServiceRegistry.set(apiServiceName, apiService);

    return true;
}

function has(apiServiceName) {
    return apiServiceRegistry.has(apiServiceName);
}

/**
 * Get a api service by its name
 *
 * @param {String|any} apiServiceName
 * @returns {any|undefined}
 */
function getByName(apiServiceName) {
    return apiServiceRegistry.get(apiServiceName);
}

function getServices() {
    return Array.from(apiServiceRegistry).reduce(
        (
            accumulator,
            [
                key,
                value,
            ],
        ) => {
            accumulator[key] = value;
            return accumulator;
        },
        {},
    );
}
