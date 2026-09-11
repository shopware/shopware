/**
 * @sw-package framework
 * @private
 */

import type { ComponentInternalInstance, EffectScope, WatchOptions, WatchCallback } from 'vue';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';

/** @private */
export type LifecycleHookFn = (...args: unknown[]) => unknown;
/** @private */
export type AnyFn = (...args: unknown[]) => unknown;
/** @private */
export type ComponentState<COMPONENT_NAME extends keyof ComponentPublicApiMapping & string = string> =
    ComponentPublicApiMapping[COMPONENT_NAME];

/** @private */
export interface ComputedObjectDefinition {
    get?: (vm?: object) => unknown;
    set?: (val: unknown) => void;
}
/** @private */
export type ComputedDefinition = ((vm?: object) => unknown) | ComputedObjectDefinition;

/** @private */
export interface WatchObjectDefinition extends WatchOptions {
    handler: WatchCallback | string;
}
/** @private */
export type SingleWatchDefinition = WatchCallback | WatchObjectDefinition | string;
/** @private */
export type WatchDefinition = SingleWatchDefinition | SingleWatchDefinition[];

/** @private */
export type InjectConfig = string[] | Record<string, string | symbol | { from?: PropertyKey; default?: unknown }>;

/** @private */
export type ProvideConfig = Record<PropertyKey, unknown> | ((this: object) => Record<PropertyKey, unknown>);

/** @private */
export type LifecycleHookName =
    | 'beforeCreate'
    | 'created'
    | 'beforeMount'
    | 'mounted'
    | 'beforeUpdate'
    | 'updated'
    | 'beforeUnmount'
    | 'unmounted'
    | 'activated'
    | 'deactivated'
    | 'errorCaptured'
    | 'beforeDestroy'
    | 'destroyed'
    | 'renderTracked'
    | 'renderTriggered'
    | 'serverPrefetch';

/** Extended config that types lifecycle hook properties directly to avoid explicit casts. */
/** @private */
export type ExtendedComponentConfig = ComponentConfig & {
    [K in LifecycleHookName]?: LifecycleHookFn;
};

/** @private */
export type OverrideFn<COMPONENT_NAME extends keyof ComponentPublicApiMapping & string = string> = (
    previousState: ComponentState<COMPONENT_NAME>,
    props: ComponentState<COMPONENT_NAME>,
    context?: unknown,
    owner?: LegacyOverrideOwner,
) => ComponentState<COMPONENT_NAME>;

/** @private */
export interface MergedConfig
    extends Omit<ComponentConfig, 'data' | 'computed' | 'methods' | 'watch' | 'inject' | 'provide'> {
    data?: (this: object, vm: object) => Record<string, unknown>;
    computed?: Record<string, ComputedDefinition>;
    methods?: Record<string, AnyFn>;
    watch?: Record<string, WatchDefinition>;
    inject?: InjectConfig;
    provide?: ProvideConfig;
    _lifecycleHooks?: Partial<Record<LifecycleHookName, LifecycleHookFn[]>>;
}

/** @private */
export interface LegacyOverrideOwner {
    instance: ComponentInternalInstance | null;
    initializing?: boolean;
    state: ComponentState;
    privateKeys?: ReadonlySet<string>;
    scope?: EffectScope;
    data?: ComponentState;
    options?: ComponentConfig;
}

declare module 'vue' {
    interface ComponentCustomOptions {
        /** Maps an original Options API instance member to its migrated setup binding. */
        legacyOptionsBindings?: Record<string, string>;
        __swExtendable?: boolean;
    }
}
