/**
 * @sw-package framework
 *
 * Options API to Composition API Override Shim
 *
 * This module provides a compatibility layer that allows Options API component overrides
 * to work transparently when the target component uses Composition API with createExtendableSetup().
 *
 * @experimental stableVersion:v6.8.0 feature:ADMIN_COMPOSITION_API_EXTENSION_SYSTEM
 */

/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-return, @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-argument, max-len */

import {
    ref,
    computed,
    watch,
    isRef,
    reactive,
    getCurrentInstance,
    onBeforeMount,
    onMounted,
    onBeforeUpdate,
    onUpdated,
    onBeforeUnmount,
    onUnmounted,
    onActivated,
    onDeactivated,
    onErrorCaptured,
} from 'vue';
import type { Ref, ComputedRef } from 'vue';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';

/**
 * Maps Options API lifecycle hook names to their Composition API equivalents.
 * `null` means the hook runs immediately (beforeCreate/created happen during setup).
 */
const LIFECYCLE_HOOK_MAP: Record<string, ((...args: any[]) => any) | null> = {
    beforeCreate: null,
    created: null,
    beforeMount: onBeforeMount,
    mounted: onMounted,
    beforeUpdate: onBeforeUpdate,
    updated: onUpdated,
    beforeUnmount: onBeforeUnmount,
    unmounted: onUnmounted,
    activated: onActivated,
    deactivated: onDeactivated,
    errorCaptured: onErrorCaptured,
};

const LIFECYCLE_HOOKS = Object.keys(LIFECYCLE_HOOK_MAP);

interface MergedConfig extends ComponentConfig {
    _lifecycleHooks?: Record<string, ((...args: any[]) => void)[]>;
}

/**
 * Track components using Composition API
 * @private
 */
export const _compositionApiComponents = reactive(new Set<string>());

/**
 * @private
 * Detects if the shim should be activated for a component override
 *
 * @param componentName - Name of the component being overridden
 * @param overrideConfig - The override configuration object
 * @returns true if shim should activate, false otherwise
 */
export function shouldActivateShim(componentName: string, overrideConfig: ComponentConfig): boolean {
    const targetUsesCompositionApi = _compositionApiComponents.has(componentName);

    const hasLifecycleHooks = LIFECYCLE_HOOKS.some((hook) => !!(overrideConfig as any)[hook]);
    const mixinsHaveLifecycleHooks = overrideConfig.mixins?.some(
        (mixin: any) => LIFECYCLE_HOOKS.some((hook) => !!(mixin as any)[hook]),
    ) ?? false;

    const usesOptionsApi = !!(
        overrideConfig.data ||
        overrideConfig.methods ||
        overrideConfig.computed ||
        overrideConfig.watch ||
        overrideConfig.mixins ||
        overrideConfig.inject ||
        hasLifecycleHooks ||
        mixinsHaveLifecycleHooks
    );

    return targetUsesCompositionApi && usesOptionsApi;
}

/**
 * @private
 * Main conversion function that transforms Options API override to Composition API
 *
 * @param componentName - Name of the component being overridden
 * @param optionsConfig - Options API configuration object
 * @returns Composition API override function
 */
export function convertOptionsApiOverrideToCompositionApi(
    componentName: string,
    optionsConfig: ComponentConfig,
): (previousState: any, props: any, context?: any) => any {
    logDeprecationWarning(componentName);
    checkUnsupportedFeatures(componentName, optionsConfig);

    return (previousState: any, props: any) => {
        const result: Record<string, any> = {};

        const mergedConfig = mergeMixins(optionsConfig);

        if (mergedConfig.data) {
            Object.assign(result, convertData(mergedConfig.data));
        }

        // Create the this proxy (needs to be created after data but before computed/methods)
        const thisProxy = createThisProxy(previousState, props, result);

        if (mergedConfig.computed) {
            Object.assign(result, convertComputed(mergedConfig.computed, thisProxy));
        }

        if (mergedConfig.methods) {
            Object.assign(result, convertMethods(mergedConfig.methods, thisProxy));
        }

        if (mergedConfig.watch) {
            setupWatchers(mergedConfig.watch, thisProxy);
        }

        if (mergedConfig._lifecycleHooks) {
            setupLifecycleHooks(mergedConfig._lifecycleHooks, thisProxy);
        }

        if (mergedConfig.inject) {
            result._inject = mergedConfig.inject;
        }

        return result;
    };
}

/**
 * Merges mixins into the component configuration
 */
function mergeMixins(config: ComponentConfig): MergedConfig {
    const lifecycleHooks: Record<string, ((...args: any[]) => void)[]> = {};

    const merged: MergedConfig = {
        data: config.data,
        methods: { ...config.methods },
        computed: { ...config.computed },
        watch: { ...config.watch },
        inject: config.inject,
    };

    if (config.mixins && config.mixins.length > 0) {
        config.mixins.forEach((mixin: ComponentConfig) => {
            // Collect lifecycle hooks from mixin (mixin hooks fire before component hooks)
            LIFECYCLE_HOOKS.forEach((hook: string) => {
                if ((mixin as any)[hook]) {
                    if (!lifecycleHooks[hook]) {
                        lifecycleHooks[hook] = [];
                    }
                    lifecycleHooks[hook].push((mixin as any)[hook]);
                }
            });

            const existingDataValue =
                merged.data && typeof merged.data === 'function' ? (merged.data as () => any)() : (merged.data ?? {});

            if (mixin.data) {
                const mixinData = typeof mixin.data === 'function' ? (mixin.data as () => any)() : mixin.data;
                merged.data = () => ({ ...mixinData, ...existingDataValue });
            }

            if (mixin.methods) {
                merged.methods = { ...mixin.methods, ...merged.methods };
            }

            if (mixin.computed) {
                merged.computed = { ...mixin.computed, ...merged.computed };
            }

            if (mixin.watch) {
                merged.watch = { ...mixin.watch, ...merged.watch };
            }
        });
    }

    // Component's own hooks go last (after mixin hooks), matching Vue's merge strategy
    LIFECYCLE_HOOKS.forEach((hook: string) => {
        if ((config as any)[hook]) {
            if (!lifecycleHooks[hook]) {
                lifecycleHooks[hook] = [];
            }
            lifecycleHooks[hook].push((config as any)[hook]);
        }
    });

    if (Object.keys(lifecycleHooks).length > 0) {
        merged._lifecycleHooks = lifecycleHooks;
    }

    return merged;
}

/**
 * Converts Options API methods to Composition API functions
 */
function convertMethods(
    methods: Record<string, (...args: any[]) => any>,
    thisProxy: any,
): Record<string, (...args: any[]) => any> {
    const converted: Record<string, (...args: any[]) => any> = {};

    Object.entries(methods).forEach(
        ([
            name,
            method,
        ]: [
            string,
            (...args: any[]) => any,
        ]) => {
            converted[name] = function (this: any, ...args: any[]) {
                // Bind `this` to proxy that maps to previousState
                return method.call(thisProxy, ...args);
            };
        },
    );

    return converted;
}

/**
 * Creates a proxy that maps `this` access to previousState refs
 */
function createThisProxy(previousState: any, props: any, localState: any): any {
    return new Proxy(
        {},
        {
            get(target: any, prop: string | symbol) {
                if (typeof prop !== 'string') {
                    return undefined;
                }

                // Handle $super calls
                if (prop === '$super') {
                    return (methodName: string, ...args: any[]) => {
                        if (previousState[methodName] && typeof previousState[methodName] === 'function') {
                            return previousState[methodName](...args);
                        }

                        // Support $super for computed properties (refs/computedRefs)
                        if (previousState[methodName] !== undefined && isRef(previousState[methodName])) {
                            return previousState[methodName].value;
                        }

                        throw new Error(`$super: method "${methodName}" not found in previous state`);
                    };
                }

                // Check local state first (data, computed, methods from override)
                if (localState[prop] !== undefined) {
                    return unwrapRef(localState[prop]);
                }

                // Check props
                if (props[prop] !== undefined) {
                    return props[prop];
                }

                // Check previousState (from Composition API)
                if (previousState[prop] !== undefined) {
                    return unwrapRef(previousState[prop]);
                }

                if (!prop.startsWith('_') && !prop.startsWith('$')) {
                    console.warn(
                        `[Options API Shim] Property "${prop}" not found in component state. ` +
                            `This may indicate accessing private/unexposed state.`,
                    );
                }

                return undefined;
            },
            set(target: any, prop: string | symbol, value: any) {
                if (typeof prop !== 'string') {
                    return false;
                }

                if (localState[prop] !== undefined) {
                    if (isRef(localState[prop])) {
                        localState[prop].value = value;
                        return true;
                    }
                    localState[prop] = value;
                    return true;
                }

                if (previousState[prop] !== undefined) {
                    if (isRef(previousState[prop])) {
                        previousState[prop].value = value;
                        return true;
                    }
                    console.error(`[Options API Shim] Cannot set property "${prop}" - property is not a ref or is readonly`);
                    return false;
                }

                console.error(`[Options API Shim] Cannot set property "${prop}" - property not found in component state`);
                return false;
            },
        },
    );
}

/**
 * Helper to unwrap refs for property access
 */
function unwrapRef(value: any): any {
    return isRef(value) ? value.value : value;
}

/**
 * Converts Options API computed properties to Composition API computed refs
 */
function convertComputed(
    computedDefs: Record<string, any>,
    thisProxy: any,
): Record<string, ComputedRef> {
    const converted: Record<string, ComputedRef> = {};

    Object.entries(computedDefs).forEach(
        ([
            name,
            computedDef,
        ]: [
            string,
            any,
        ]) => {
            if (typeof computedDef === 'function') {
                // Simple getter
                converted[name] = computed(() => computedDef.call(thisProxy));
            } else if (computedDef && typeof computedDef === 'object' && (computedDef.get || computedDef.set)) {
                // Getter/setter
                const getter = computedDef.get ? () => computedDef.get.call(thisProxy) : undefined;
                const setter = computedDef.set ? (val: any) => computedDef.set.call(thisProxy, val) : undefined;

                if (getter && setter) {
                    converted[name] = computed({
                        get: getter,
                        set: setter,
                    });
                } else if (getter) {
                    converted[name] = computed(getter);
                }
            }
        },
    );

    return converted;
}

/**
 * Converts Options API data() function to refs
 */
function convertData(dataFn: (() => Record<string, any>) | Record<string, any>): Record<string, Ref> {
    const data = typeof dataFn === 'function' ? dataFn() : dataFn;
    const converted: Record<string, Ref> = {};

    if (!data || typeof data !== 'object') {
        return converted;
    }

    Object.entries(data).forEach(
        ([
            key,
            value,
        ]: [
            string,
            any,
        ]) => {
            converted[key] = ref(value);
        },
    );

    return converted;
}

/**
 * Sets up watchers for Options API watch configuration
 */
function setupWatchers(watchConfig: Record<string, any>, thisProxy: any): void {
    Object.entries(watchConfig).forEach(
        ([
            key,
            handler,
        ]: [
            string,
            any,
        ]) => {
            const source = () => thisProxy[key];

            if (typeof handler === 'function') {
                // Simple function handler
                watch(source, (newVal: any, oldVal: any) => {
                    handler.call(thisProxy, newVal, oldVal);
                });
            } else if (typeof handler === 'object' && handler.handler) {
                // Handler with options (immediate, deep, etc.)
                const options: any = {
                    immediate: handler.immediate,
                    deep: handler.deep,
                };

                watch(
                    source,
                    (newVal: any, oldVal: any) => {
                        handler.handler.call(thisProxy, newVal, oldVal);
                    },
                    options,
                );
            } else if (typeof handler === 'string') {
                // String method name
                watch(source, (newVal: any, oldVal: any) => {
                    if (thisProxy[handler] && typeof thisProxy[handler] === 'function') {
                        thisProxy[handler](newVal, oldVal);
                    }
                });
            }
        },
    );
}

/**
 * Hooks that have already executed by the time the component is mounted.
 * If the override is applied late (after setup), these are called immediately.
 */
const ALREADY_PASSED_WHEN_MOUNTED = new Set(['beforeCreate', 'created', 'beforeMount', 'mounted']);

/**
 * Registers Options API lifecycle hooks using their Composition API equivalents.
 * Hooks mapped to `null` (beforeCreate, created) are called immediately since
 * setup() is the Composition API equivalent of both.
 *
 * When the override is applied late (after setup has returned, e.g. via the
 * async override registry processing), `getCurrentInstance()` returns null
 * and `on*` registration functions cannot be used. In that case:
 * - Hooks that have already passed (beforeCreate, created, beforeMount, mounted)
 *   are invoked immediately.
 * - Future hooks (beforeUnmount, unmounted, etc.) cannot be registered and
 *   a warning is logged.
 */
function setupLifecycleHooks(
    hooks: Record<string, ((...args: any[]) => void)[]>,
    thisProxy: any,
): void {
    const instance = getCurrentInstance();

    Object.entries(hooks).forEach(([hookName, handlers]) => {
        const compositionHook = LIFECYCLE_HOOK_MAP[hookName];

        handlers.forEach((handler) => {
            if (compositionHook === null) {
                handler.call(thisProxy);
                return;
            }

            if (instance) {
                compositionHook(() => handler.call(thisProxy));
            } else if (ALREADY_PASSED_WHEN_MOUNTED.has(hookName)) {
                handler.call(thisProxy);
            } else {
                console.warn(
                    `[Options API Shim] Lifecycle hook "${hookName}" could not be registered because ` +
                        `the override was applied after setup(). Only beforeCreate, created, beforeMount, ` +
                        `and mounted are supported for late-applied overrides.`,
                );
            }
        });
    });
}

/**
 * Checks for unsupported features and logs appropriate errors
 */
function checkUnsupportedFeatures(componentName: string, config: ComponentConfig): void {
    if (config.render && typeof config.render === 'function') {
        console.error(
            `[Options API Shim] Custom render() functions are not supported by the compatibility shim. ` +
                `Component "${componentName}" will not work correctly. ` +
                `Please migrate to Composition API.`,
        );
    }

    if ((config as any).$refs || (config as any).$el) {
        console.warn(
            `[Options API Shim] $refs and $el are not available in the setup() context. ` +
                `Component "${componentName}" may not work correctly if it relies on these.`,
        );
    }
}

/**
 * Logs deprecation warning when shim activates
 */
function logDeprecationWarning(componentName: string): void {
    console.warn(
        `[Deprecation Warning] Component "${componentName}" is being overridden with Options API patterns, ` +
            `but the target uses Composition API. A compatibility shim has been activated. ` +
            `This is a temporary solution and may have limitations. ` +
            `Please migrate your override to use Shopware.Component.overrideComponentSetup(). ` +
            `See: https://developer.shopware.com/docs/resources/references/core-reference/administration-reference/composition-api`,
    );
}
