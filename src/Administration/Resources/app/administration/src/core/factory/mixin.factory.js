/**
 * @module core/factory/mixin
 */
import { warn } from 'src/core/service/utils/debug.utils';

export default {
    register,
    getByName,
    getMixinRegistry,
};

/**
 * Registry which holds all mixins
 *
 * @type {Map}
 */
const mixinRegistry = new Map();

/**
 * Get the complete mixin registry
 *
 * @returns {Map}
 */
function getMixinRegistry() {
    return mixinRegistry;
}

/**
 * Register a new mixin
 *
 * @param {String} mixinName
 * @param {Object} [mixin={}]
 * @returns {Boolean|Object}
 */
function register(mixinName, mixin = {}) {
    if (!mixinName || !mixinName.length) {
        warn(
            'MixinFactory',
            'A mixin always needs a name.',
            mixin,
        );
        return false;
    }

    if (mixinRegistry.has(mixinName)) {
        warn(
            'MixinFactory',
            `The mixin "${mixinName}" is already registered. Please select a unique name for your mixin.`,
            mixin,
        );
        return false;
    }

    mixinRegistry.set(mixinName, mixin);

    return mixin;
}

/**
 * Get a mixin by its name
 *
 * @param mixinName
 * @returns {any | undefined}
 */
function getByName(mixinName) {
    if (!mixinRegistry.has(mixinName)) {
        throw new Error(`The mixin "${mixinName}" is not registered.`);
    }

    return mixinRegistry.get(mixinName);
}
