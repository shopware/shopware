/**
 * @module core/factory/filter
 */
import { warn } from 'src/core/service/utils/debug.utils';

export default {
    getRegistry,
    register,
    getByName,
};

/**
 * Registry which holds all filter
 *
 * @type {Map<any, any>}
 */
const filterRegistry = new Map();

/**
 * Empty function, used as the default parameter for the register method
 *
 * @type {Function}
 */
const noop = () => {};

/**
 * Factory name
 * @type {string}
 */
const name = 'FilterFactory';

/**
 * Get the complete filter registry
 *
 * @returns {Map<String, Function>}
 */
function getRegistry() {
    return filterRegistry;
}

/**
 * Register a new filter
 *
 * @param {String} filterName
 * @param {Function} [filterFactoryMethod=function]
 * @returns {boolean}
 */
function register(filterName, filterFactoryMethod = noop) {
    if (!filterName || !filterName.length) {
        warn(
            name,
            'A filter always needs a name',
        );
        return false;
    }

    if (filterRegistry.has(filterName)) {
        warn(
            name,
            `The filter "${filterName}" is already registered. Please select a unique name for your filter.`,
        );
        return false;
    }

    filterRegistry.set(filterName, filterFactoryMethod);

    return true;
}

/**
 * Get a mixin by its name
 *
 * @param {String|any} filterName
 * @returns {any|undefined}
 */
function getByName(filterName) {
    return filterRegistry.get(filterName);
}
