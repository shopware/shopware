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
    inject as vueInject,
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
import type { Ref, ComputedRef, WatchOptions } from 'vue';
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
 * @private
 * Detects if the shim should be activated for a component override.
 * Returns true when the override config contains Options API patterns.
 * The caller (createExtendableSetup) already knows it is inside a Composition API component.
 *
 * @param overrideConfig - The override configuration object
 * @returns true if shim should activate, false otherwise
 */
export function shouldActivateShim(overrideConfig: ComponentConfig): boolean {
    const hasLifecycleHooks = LIFECYCLE_HOOKS.some((hook) => !!(overrideConfig as any)[hook]);

    return !!(
        overrideConfig.data ||
        overrideConfig.methods ||
        overrideConfig.computed ||
        overrideConfig.watch ||
        (overrideConfig.mixins && overrideConfig.mixins.length > 0) ||
        overrideConfig.inject ||
        // Include extends so checkUnsupportedFeatures() can emit its warning
        (overrideConfig as any).extends ||
        hasLifecycleHooks
    );
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

        // Resolve inject values from Vue's provide/inject system.
        // This must run while we are still inside the component's setup() context
        // (the immediate watch in createExtendableSetup guarantees this).
        const injectedValues = resolveInject(mergedConfig.inject);

        // Create the this proxy (needs to be created after data but before computed/methods)
        const thisProxy = createThisProxy(previousState, props, result, injectedValues);

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

        return result;
    };
}

/**
 * Recursively flattens a mixin and all of its nested mixins into a flat ordered array.
 * Nested mixins are resolved depth-first so that the deepest ancestor appears first,
 * matching Vue's own mixin merge strategy.
 */
function flattenMixins(mixin: ComponentConfig): ComponentConfig[] {
    const nested = mixin.mixins ? mixin.mixins.flatMap((m) => flattenMixins(m as ComponentConfig)) : [];
    return [
        ...nested,
        mixin,
    ];
}

/**
 * Resolves Options API inject config into a plain map of key → value.
 * Supports all three Vue inject forms: array, object-with-string, object-with-options.
 * Must be called during component setup() to have access to the provide/inject chain.
 */
function resolveInject(injectConfig: ComponentConfig['inject']): Record<string, any> {
    const resolved: Record<string, any> = {};

    if (!injectConfig) {
        return resolved;
    }

    if (Array.isArray(injectConfig)) {
        injectConfig.forEach((key: string) => {
            resolved[key] = vueInject(key);
        });
    } else {
        Object.entries(injectConfig as Record<string, any>).forEach(
            ([
                localKey,
                spec,
            ]) => {
                if (typeof spec === 'string') {
                    // { localKey: 'provideKey' }
                    resolved[localKey] = vueInject(spec);
                } else if (spec && typeof spec === 'object') {
                    // { localKey: { from: 'provideKey', default: fallback } }
                    const from = spec.from ?? localKey;
                    const hasDefault = Object.prototype.hasOwnProperty.call(spec, 'default');
                    resolved[localKey] = hasDefault ? vueInject(from, spec.default) : vueInject(from);
                } else {
                    resolved[localKey] = vueInject(localKey);
                }
            },
        );
    }

    return resolved;
}

/**
 * Merges two inject configurations (array or object form) into a single normalized object.
 * Existing (component-level) entries win on conflict, matching Vue's merge strategy.
 */
function mergeInjectConfigs(existing: ComponentConfig['inject'], incoming: ComponentConfig['inject']): Record<string, any> {
    const normalized: Record<string, any> = {};

    if (Array.isArray(existing)) {
        existing.forEach((key: string) => {
            normalized[key] = key;
        });
    } else if (existing && typeof existing === 'object') {
        Object.assign(normalized, existing);
    }

    if (Array.isArray(incoming)) {
        incoming.forEach((key: string) => {
            if (!Object.prototype.hasOwnProperty.call(normalized, key)) {
                normalized[key] = key;
            }
        });
    } else if (incoming && typeof incoming === 'object') {
        Object.entries(incoming as Record<string, any>).forEach(
            ([
                key,
                val,
            ]) => {
                if (!Object.prototype.hasOwnProperty.call(normalized, key)) {
                    normalized[key] = val;
                }
            },
        );
    }

    return normalized;
}

/**
 * Merges mixins into the component configuration
 */
function mergeMixins(config: ComponentConfig): MergedConfig {
    const lifecycleHooks: Record<string, ((...args: any[]) => void)[]> = {};
    // Collect data factories in merge order so each is called exactly once.
    // Mixin factories are pushed first (deepest ancestor first via flattenMixins),
    // then the component's own factory last — so component keys win on conflict.
    const allDataFns: Array<() => Record<string, any>> = [];

    const merged: MergedConfig = {
        methods: { ...config.methods },
        computed: { ...config.computed },
        watch: { ...config.watch },
        inject: config.inject,
    };

    if (config.mixins && config.mixins.length > 0) {
        const allMixins = config.mixins.flatMap((m) => flattenMixins(m as ComponentConfig));
        allMixins.forEach((mixin: ComponentConfig) => {
            // Collect lifecycle hooks from mixin (mixin hooks fire before component hooks)
            LIFECYCLE_HOOKS.forEach((hook: string) => {
                if ((mixin as any)[hook]) {
                    if (!lifecycleHooks[hook]) {
                        lifecycleHooks[hook] = [];
                    }
                    lifecycleHooks[hook].push((mixin as any)[hook]);
                }
            });

            // Collect the mixin's data factory without calling it yet
            if (mixin.data) {
                allDataFns.push(typeof mixin.data === 'function' ? (mixin.data as () => any) : () => mixin.data);
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

            if (mixin.inject) {
                merged.inject = mergeInjectConfigs(merged.inject, mixin.inject);
            }
        });
    }

    // Add the component's own data factory last so its keys win over mixin keys
    if (config.data) {
        allDataFns.push(typeof config.data === 'function' ? (config.data as () => any) : () => config.data);
    }

    // Produce a single merged factory that calls each original factory exactly once
    if (allDataFns.length > 0) {
        merged.data = () => allDataFns.reduce<Record<string, any>>((acc, fn) => ({ ...acc, ...fn() }), {});
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
 * Creates a proxy that maps `this` access to previousState refs.
 * Captures the current component instance at creation time so that
 * Vue instance properties ($emit, $t, $route, etc.) remain available
 * even when accessed outside the setup() context (e.g. in event handlers).
 */
function createThisProxy(previousState: any, props: any, localState: any, injectedValues: Record<string, any> = {}): any {
    const componentInstance = getCurrentInstance();

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

                // Forward Vue instance properties ($emit, $t, $tc, $route, $router, $refs, $nextTick, etc.)
                if (prop.startsWith('$')) {
                    if (componentInstance?.proxy && prop in componentInstance.proxy) {
                        return (componentInstance.proxy as any)[prop];
                    }
                    return undefined;
                }

                // Check local state first (data, computed, methods from override)
                if (prop in localState) {
                    return unwrapRef(localState[prop]);
                }

                // Check injected values (from Options API inject config)
                if (Object.prototype.hasOwnProperty.call(injectedValues, prop)) {
                    return injectedValues[prop];
                }

                // Check props
                if (Object.prototype.hasOwnProperty.call(props, prop)) {
                    return props[prop];
                }

                // Check previousState (from Composition API)
                if (prop in previousState) {
                    return unwrapRef(previousState[prop]);
                }

                if (!prop.startsWith('_')) {
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

                if (prop in localState) {
                    if (isRef(localState[prop])) {
                        localState[prop].value = value;
                        return true;
                    }
                    localState[prop] = value;
                    return true;
                }

                if (prop in previousState) {
                    if (isRef(previousState[prop])) {
                        previousState[prop].value = value;
                        return true;
                    }
                    console.error(`[Options API Shim] Cannot set property "${prop}" - property is not a ref or is readonly`);
                    return false;
                }

                if (Object.prototype.hasOwnProperty.call(props, prop)) {
                    console.error(
                        `[Options API Shim] Cannot set property "${prop}" - it is a component prop and is read-only.`,
                    );
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
function convertComputed(computedDefs: Record<string, any>, thisProxy: any): Record<string, ComputedRef> {
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
 * Registers a single watcher from an Options API watch handler definition.
 * Handles function, object-with-options, and string-method-name forms.
 */
function registerSingleWatcher(source: () => any, handler: any, thisProxy: any): void {
    if (typeof handler === 'function') {
        watch(source, (newVal: any, oldVal: any) => {
            handler.call(thisProxy, newVal, oldVal);
        });
    } else if (typeof handler === 'object' && handler.handler) {
        const options: WatchOptions = {};
        if (handler.immediate !== undefined) options.immediate = handler.immediate;
        if (handler.deep !== undefined) options.deep = handler.deep;
        if (handler.flush !== undefined) options.flush = handler.flush;

        watch(
            source,
            (newVal: any, oldVal: any) => {
                handler.handler.call(thisProxy, newVal, oldVal);
            },
            options,
        );
    } else if (typeof handler === 'string') {
        watch(source, (newVal: any, oldVal: any) => {
            if (thisProxy[handler] && typeof thisProxy[handler] === 'function') {
                thisProxy[handler](newVal, oldVal);
            }
        });
    }
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
            if (key.includes('.')) {
                console.warn(
                    `[Options API Shim] Dot-notation watch path "${key}" is not supported by the compatibility shim. ` +
                        `Please migrate your watcher to Composition API.`,
                );
                return;
            }

            const source = () => thisProxy[key];

            if (Array.isArray(handler)) {
                handler.forEach((h) => registerSingleWatcher(source, h, thisProxy));
            } else {
                registerSingleWatcher(source, handler, thisProxy);
            }
        },
    );
}

/**
 * Hooks that have already executed by the time the component is mounted.
 * If the override is applied late (after setup), these are called immediately.
 */
const ALREADY_PASSED_WHEN_MOUNTED = new Set([
    'beforeCreate',
    'created',
    'beforeMount',
    'mounted',
]);

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
function setupLifecycleHooks(hooks: Record<string, ((...args: any[]) => void)[]>, thisProxy: any): void {
    const instance = getCurrentInstance();

    Object.entries(hooks).forEach(
        ([
            hookName,
            handlers,
        ]) => {
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
        },
    );
}

const UNSUPPORTED_OPTIONS = [
    'components',
    'directives',
    'provide',
    'template',
    'extends',
    'inheritAttrs',
    'emits',
] as const;

/**
 * Checks for unsupported features and logs appropriate errors/warnings
 */
function checkUnsupportedFeatures(componentName: string, config: ComponentConfig): void {
    if (config.render && typeof config.render === 'function') {
        console.error(
            `[Options API Shim] Custom render() functions are not supported by the compatibility shim. ` +
                `Component "${componentName}" will not work correctly. ` +
                `Please migrate to Composition API.`,
        );
    }

    UNSUPPORTED_OPTIONS.forEach((key) => {
        if ((config as any)[key]) {
            console.warn(
                `[Options API Shim] "${key}" is not supported by the compatibility shim ` +
                    `in component "${componentName}". This option will be ignored.`,
            );
        }
    });
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
