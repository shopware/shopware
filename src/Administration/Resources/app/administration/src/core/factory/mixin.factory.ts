/**
 * @package admin
 *
 * @module core/factory/mixin
 */
import { warn } from 'src/core/service/utils/debug.utils';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    register,
    getByName,
    getMixinRegistry,
};

/**
 * Registry which holds all mixins
 */
const mixinRegistry = new Map<string, unknown>();

function addToMixinRegistry<T extends keyof MixinContainer>(mixinName: T, mixin: MixinContainer[T]): void {
    mixinRegistry.set(mixinName, mixin);
}

/**
 * Get the complete mixin registry
 */
function getMixinRegistry(): Map<string, unknown> {
    return mixinRegistry;
}

/**
 * Register a new mixin
 */
// eslint-disable-next-line max-len
function register<T, MixinName extends keyof MixinContainer>(mixinName: MixinName, mixin: T) {
    if (mixinRegistry.has(mixinName)) {
        warn(
            'MixinFactory',
            `The mixin "${mixinName}" is already registered. Please select a unique name for your mixin.`,
            mixin,
        );

        return mixinRegistry.get(mixinName) as T;
    }

    addToMixinRegistry(mixinName, mixin);

    return mixin;
}

/**
 * Get a mixin by its name
 */
// eslint-disable-next-line max-len
function getByName<MN extends keyof MixinContainer>(mixinName: MN): MixinContainer[MN] {
    if (!mixinRegistry.has(mixinName) || mixinRegistry.get(mixinName) === undefined) {
        throw new Error(`The mixin "${mixinName}" is not registered.`);
    }

    return mixinRegistry.get(mixinName);
}
