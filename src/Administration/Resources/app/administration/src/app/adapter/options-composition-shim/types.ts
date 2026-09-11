/**
 * @sw-package framework
 * @private
 */

import type { ComponentConfig } from 'src/core/factory/async-component.factory';

/** @private */
export type LifecycleHookFn = (...args: unknown[]) => void;
/** @private */
export type AnyFn = (...args: unknown[]) => unknown;
/** @private */
export type ComponentState<COMPONENT_NAME extends keyof ComponentPublicApiMapping & string = string> =
    ComponentPublicApiMapping[COMPONENT_NAME];

/** @private */
export interface ComputedObjectDefinition {
    get?: () => unknown;
    set?: (val: unknown) => void;
}
/** @private */
export type ComputedDefinition = (() => unknown) | ComputedObjectDefinition;

/** @private */
export interface WatchObjectDefinition {
    handler: (newVal: unknown, oldVal: unknown) => void;
    immediate?: boolean;
    deep?: boolean;
    flush?: 'pre' | 'post' | 'sync';
}
/** @private */
export type SingleWatchDefinition = ((newVal: unknown, oldVal: unknown) => void) | WatchObjectDefinition | string;
/** @private */
export type WatchDefinition = SingleWatchDefinition | SingleWatchDefinition[];

/** @private */
export type InjectConfig = ComponentConfig['inject'];

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
    | 'errorCaptured';

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
) => ComponentState<COMPONENT_NAME>;

/** @private */
export interface MergedConfig extends Omit<ComponentConfig, 'data' | 'computed' | 'methods' | 'watch' | 'inject'> {
    data?: () => Record<string, unknown>;
    computed?: Record<string, ComputedDefinition>;
    methods?: Record<string, AnyFn>;
    watch?: Record<string, WatchDefinition>;
    inject?: InjectConfig;
    _lifecycleHooks?: Partial<Record<LifecycleHookName, LifecycleHookFn[]>>;
}
