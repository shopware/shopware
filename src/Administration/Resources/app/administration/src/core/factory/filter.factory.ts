/**
 * @package admin
 *
 * @module core/factory/filter
 */
import { warn } from 'src/core/service/utils/debug.utils';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    getRegistry,
    register,
    getByName,
};

interface FilterRegistry extends Map<string, FilterTypes[keyof FilterTypes]> {
    get: <A extends keyof FilterTypes>(key: A) => FilterTypes[A];
}

/**
 * @description Registry which holds all filter
 */
const filterRegistry: FilterRegistry = new Map();

/**
 * Factory name
 */
const name = 'FilterFactory';

/**
 * Get the complete filter registry
 */
function getRegistry(): FilterRegistry {
    return filterRegistry;
}

/**
 * @description Register a new filter
 */
function register<A extends string>(filterName: A, filterFactoryMethod: FilterTypes[A]): boolean {
    if (!filterName || !filterName.length) {
        warn(name, 'A filter always needs a name');
        return false;
    }

    if (filterRegistry.has(filterName)) {
        warn(name, `The filter "${filterName}" is already registered. Please select a unique name for your filter.`);
        return false;
    }

    filterRegistry.set(filterName, filterFactoryMethod);

    return true;
}

/**
 * @description Get a filter by its name
 */
function getByName<A extends keyof FilterTypes>(filterName: A): FilterTypes[A] {
    return filterRegistry.get(filterName);
}
