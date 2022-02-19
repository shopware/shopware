/**
 * @module core/factory/filter
 */
import { warn } from 'src/core/service/utils/debug.utils';

export default {
    getRegistry,
    register,
    getByName,
};


interface FilterRegistry extends Map<string, FilterTypes[keyof FilterTypes]> {
    get: <A extends string>(key: A) => FilterTypes[A];
}

/**
 * @description Registry which holds all filter
 */
const filterRegistry: FilterRegistry = new Map();

/**
 * @description Empty function, used as the default parameter for the register method
 *
 * @deprecated tag:v6.5.0 - will be removed
 */
// eslint-disable-next-line @typescript-eslint/no-empty-function
const noop = (): void => {};

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
 *
 * @deprecated tag:v6.5.0 - second parameter filterFactoryMethod will be required in future version
 */
function register<A extends string>(
    filterName: A,
    filterFactoryMethod: FilterTypes[A] = noop,
): boolean {
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
 * @description Get a filter by its name
 *
 * @deprecated tag:v6.5.0 - return type noopType will be removed
 */
function getByName<A extends string>(filterName: A): FilterTypes[A] {
    return filterRegistry.get(filterName);
}
