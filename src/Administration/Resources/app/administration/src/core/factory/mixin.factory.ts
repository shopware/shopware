/**
 * @package admin
 *
 * @module core/factory/mixin
 */
import { warn } from 'src/core/service/utils/debug.utils';
import type Vue from 'vue';
import type { ComponentOptions } from 'vue';
import type {
    ThisTypedComponentOptionsWithRecordProps,
    ThisTypedComponentOptionsWithArrayProps,
} from 'vue/types/options';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    register,
    getByName,
    getMixinRegistry,
};

/**
 * Registry which holds all mixins
 */
const mixinRegistry = new Map<string, ComponentOptions<Vue>>();

/**
 * Get the complete mixin registry
 */
function getMixinRegistry(): Map<string, ComponentOptions<Vue>> {
    return mixinRegistry;
}

/**
 * Register a new mixin
 */
// eslint-disable-next-line max-len
function register<V extends Vue, Data, Methods, Computed, PropNames extends string>(mixinName: string, mixin: ThisTypedComponentOptionsWithArrayProps<V, Data, Methods, Computed, PropNames>): boolean | ComponentOptions<V>;
// eslint-disable-next-line max-len
function register<V extends Vue, Data, Methods, Computed, Props>(mixinName: string, mixin: ThisTypedComponentOptionsWithRecordProps<V, Data, Methods, Computed, Props>): boolean | ComponentOptions<V>;
function register(mixinName: string, mixin: ComponentOptions<Vue>): boolean | ComponentOptions<Vue> {
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
 */
function getByName(mixinName: string): ComponentOptions<Vue> {
    if (!mixinRegistry.has(mixinName)) {
        throw new Error(`The mixin "${mixinName}" is not registered.`);
    }

    return mixinRegistry.get(mixinName) as ComponentOptions<Vue>;
}
