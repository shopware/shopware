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

import {
    ref,
    computed,
    watch,
    isRef,
    unref,
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

// ─── Local types ────────────────────────────────────────────────────────────

type LifecycleHookFn = (...args: unknown[]) => void;
type AnyFn = (...args: unknown[]) => unknown;
type ComponentState<COMPONENT_NAME extends keyof ComponentPublicApiMapping & string = string> =
    ComponentPublicApiMapping[COMPONENT_NAME];

interface ComputedObjectDefinition {
    get?: () => unknown;
    set?: (val: unknown) => void;
}
type ComputedDefinition = (() => unknown) | ComputedObjectDefinition;

interface WatchObjectDefinition {
    handler: (newVal: unknown, oldVal: unknown) => void;
    immediate?: boolean;
    deep?: boolean;
    flush?: 'pre' | 'post' | 'sync';
}
type SingleWatchDefinition = ((newVal: unknown, oldVal: unknown) => void) | WatchObjectDefinition | string;
type WatchDefinition = SingleWatchDefinition | SingleWatchDefinition[];

type InjectConfig = ComponentConfig['inject'];

type LifecycleHookName =
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
type ExtendedComponentConfig = ComponentConfig & {
    [K in LifecycleHookName]?: LifecycleHookFn;
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type OverrideFn<COMPONENT_NAME extends keyof ComponentPublicApiMapping & string = string> = (
    previousState: ComponentState<COMPONENT_NAME>,
    props: ComponentState<COMPONENT_NAME>,
    context?: unknown,
) => ComponentState<COMPONENT_NAME>;

// ─── Lifecycle hook registry ─────────────────────────────────────────────────

/**
 * Maps Options API lifecycle hook names to their Composition API equivalents.
 * `null` means the hook runs immediately (beforeCreate/created happen during setup).
 * `satisfies` validates value types while keeping the literal key names for LifecycleHookName.
 */
const LIFECYCLE_HOOK_MAP = {
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
} satisfies Record<string, ((fn: () => void) => void) | null>;

const LIFECYCLE_HOOKS = Object.keys(LIFECYCLE_HOOK_MAP) as LifecycleHookName[];

/**
 * Options API property keys that indicate an override is using Options API patterns.
 * `extends` is included so checkUnsupportedFeatures() can emit its warning.
 */
const OPTION_KEYS = [
    'data',
    'methods',
    'computed',
    'watch',
    'mixins',
    'inject',
    'extends',
] as const;

interface MergedConfig extends Omit<ComponentConfig, 'data' | 'computed' | 'methods' | 'watch' | 'inject'> {
    data?: () => Record<string, unknown>;
    computed?: Record<string, ComputedDefinition>;
    methods?: Record<string, AnyFn>;
    watch?: Record<string, WatchDefinition>;
    inject?: InjectConfig;
    _lifecycleHooks?: Partial<Record<LifecycleHookName, LifecycleHookFn[]>>;
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
    const extended = overrideConfig as ExtendedComponentConfig;
    const hasOptionKeys = OPTION_KEYS.some((key) => {
        const val: unknown = extended[key];
        return Array.isArray(val) ? val.length > 0 : !!val;
    });
    const hasLifecycleHooks = LIFECYCLE_HOOKS.some((hook) => !!extended[hook]);

    return hasOptionKeys || hasLifecycleHooks;
}

/**
 * @private
 * Main conversion function that transforms Options API override to Composition API
 *
 * @param componentName - Name of the component being overridden
 * @param optionsConfig - Options API configuration object
 * @returns Composition API override function
 */
export function convertOptionsApiOverrideToCompositionApi<
    COMPONENT_NAME extends keyof ComponentPublicApiMapping & string = string,
>(componentName: COMPONENT_NAME, optionsConfig: ComponentConfig): OverrideFn {
    logDeprecationWarning(componentName);
    checkUnsupportedFeatures(componentName, optionsConfig);

    return (previousState: ComponentState, props: ComponentState): ComponentState => {
        const result: ComponentState<COMPONENT_NAME> = {} as ComponentState<COMPONENT_NAME>;

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
function resolveInject(injectConfig: InjectConfig): ComponentState {
    const resolved: ComponentState = {};

    if (!injectConfig) {
        return resolved;
    }

    if (Array.isArray(injectConfig)) {
        injectConfig.forEach((key: string) => {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            resolved[key] = vueInject(key);
        });
    } else {
        const objectConfig = injectConfig;
        Object.entries(objectConfig).forEach(
            ([
                localKey,
                spec,
            ]) => {
                if (typeof spec === 'string') {
                    // { localKey: 'provideKey' }
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                    resolved[localKey] = vueInject(spec);
                } else if (spec && typeof spec === 'object') {
                    // { localKey: { from: 'provideKey', default: fallback } }
                    const specOptions = spec as { from?: string; default?: unknown };
                    const from = specOptions.from ?? localKey;
                    const hasDefault = Object.hasOwn(specOptions, 'default');
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                    resolved[localKey] = hasDefault ? vueInject(from, specOptions.default) : vueInject(from);
                } else {
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
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
function mergeInjectConfigs(existing: InjectConfig, incoming: InjectConfig): InjectConfig {
    const normalized: Record<string, unknown> = {};

    if (Array.isArray(existing)) {
        existing.forEach((key: string) => {
            normalized[key] = key;
        });
    } else if (existing && typeof existing === 'object') {
        Object.assign(normalized, existing);
    }

    if (Array.isArray(incoming)) {
        incoming.forEach((key: string) => {
            if (!Object.hasOwn(normalized, key)) {
                normalized[key] = key;
            }
        });
    } else if (incoming && typeof incoming === 'object') {
        const incomingObj = incoming as Record<string, unknown>;
        Object.entries(incomingObj).forEach(
            ([
                key,
                val,
            ]) => {
                if (!Object.hasOwn(normalized, key)) {
                    normalized[key] = val;
                }
            },
        );
    }

    return normalized as InjectConfig;
}

/**
 * Merges mixins into the component configuration
 */
function mergeMixins(config: ComponentConfig): MergedConfig {
    const lifecycleHooks: Partial<Record<LifecycleHookName, LifecycleHookFn[]>> = {};
    // Collect data factories in merge order so each is called exactly once.
    // Mixin factories are pushed first (deepest ancestor first via flattenMixins),
    // then the component's own factory last — so component keys win on conflict.
    const allDataFns: Array<() => Record<string, unknown>> = [];

    // Vue's ComponentOptions types methods/computed/watch as `any` internally,
    // so we cast once here at the boundary and let MergedConfig carry the correct types.
    const merged: MergedConfig = {
        methods: { ...(config.methods as Record<string, AnyFn>) },
        computed: { ...(config.computed as Record<string, ComputedDefinition>) },
        watch: { ...(config.watch as Record<string, WatchDefinition>) },
        inject: config.inject,
    };

    if (config.mixins && config.mixins.length > 0) {
        const allMixins = config.mixins.flatMap((m) => flattenMixins(m as ComponentConfig));
        allMixins.forEach((mixin: ComponentConfig) => {
            const extendedMixin = mixin as ExtendedComponentConfig;

            // Collect lifecycle hooks from mixin (mixin hooks fire before component hooks)
            LIFECYCLE_HOOKS.forEach((hook) => {
                const hookFn = extendedMixin[hook];
                if (hookFn) {
                    if (!lifecycleHooks[hook]) {
                        lifecycleHooks[hook] = [];
                    }
                    lifecycleHooks[hook].push(hookFn);
                }
            });

            // Collect the mixin's data factory without calling it yet
            if (mixin.data) {
                const mixinData = mixin.data;
                allDataFns.push(
                    typeof mixinData === 'function'
                        ? () => (mixinData as unknown as () => Record<string, unknown>)()
                        : () => mixinData as unknown as Record<string, unknown>,
                );
            }

            if (mixin.methods) {
                merged.methods = { ...(mixin.methods as Record<string, AnyFn>), ...merged.methods };
            }

            if (mixin.computed) {
                merged.computed = { ...(mixin.computed as Record<string, ComputedDefinition>), ...merged.computed };
            }

            if (mixin.watch) {
                merged.watch = { ...(mixin.watch as Record<string, WatchDefinition>), ...merged.watch };
            }

            if (mixin.inject) {
                merged.inject = mergeInjectConfigs(merged.inject, mixin.inject);
            }
        });
    }

    // Add the component's own data factory last so its keys win over mixin keys
    if (config.data) {
        const configData = config.data;
        allDataFns.push(
            typeof configData === 'function'
                ? () => (configData as unknown as () => Record<string, unknown>)()
                : () => configData as unknown as Record<string, unknown>,
        );
    }

    // Produce a single merged factory that calls each original factory exactly once
    if (allDataFns.length > 0) {
        merged.data = () => allDataFns.reduce<Record<string, unknown>>((acc, fn) => ({ ...acc, ...fn() }), {});
    }

    // Component's own hooks go last (after mixin hooks), matching Vue's merge strategy
    const extendedConfig = config as ExtendedComponentConfig;
    LIFECYCLE_HOOKS.forEach((hook) => {
        const hookFn = extendedConfig[hook];
        if (hookFn) {
            if (!lifecycleHooks[hook]) {
                lifecycleHooks[hook] = [];
            }
            lifecycleHooks[hook].push(hookFn);
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
function convertMethods(methods: Record<string, AnyFn>, thisProxy: object): ComponentState {
    const converted: ComponentState = {};

    Object.entries(methods).forEach(
        ([
            name,
            method,
        ]) => {
            converted[name] = function (...args: unknown[]) {
                return method.apply(thisProxy, args);
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
function createThisProxy<COMPONENT_NAME extends keyof ComponentPublicApiMapping & string = string>(
    previousState: ComponentState<COMPONENT_NAME>,
    props: ComponentState<COMPONENT_NAME>,
    localState: ComponentState<COMPONENT_NAME>,
    injectedValues: ComponentState<COMPONENT_NAME> = {} as ComponentState<COMPONENT_NAME>,
): object {
    const componentInstance = getCurrentInstance();

    return new Proxy(
        {},
        {
            get(_target: object, prop: string | symbol): unknown {
                if (typeof prop !== 'string') {
                    return undefined;
                }

                // Handle $super calls
                if (prop === '$super') {
                    return (methodName: string, ...args: unknown[]): unknown => {
                        if (previousState[methodName] && typeof previousState[methodName] === 'function') {
                            return (previousState[methodName] as AnyFn)(...args);
                        }

                        // Support $super for computed properties (refs/computedRefs)
                        if (previousState[methodName] !== undefined && isRef(previousState[methodName])) {
                            return (previousState[methodName] as Ref).value;
                        }

                        throw new Error(
                            `$super: "${methodName}" not found in previous state. It must be a method (function) or a ref.`,
                        );
                    };
                }

                // Forward Vue instance properties ($emit, $t, $tc, $route, $router, $refs, $nextTick, etc.)
                if (prop.startsWith('$')) {
                    const proxy = componentInstance?.proxy as Record<string, unknown> | null | undefined;
                    if (proxy && prop in proxy) {
                        return proxy[prop];
                    }
                    return undefined;
                }

                // Check local state first (data, computed, methods from override)
                if (prop in localState) {
                    return unref(localState[prop]);
                }

                // Check injected values (from Options API inject config)
                if (Object.hasOwn(injectedValues, prop)) {
                    return injectedValues[prop];
                }

                // Check props
                if (Object.hasOwn(props, prop)) {
                    return props[prop];
                }

                // Check previousState (from Composition API)
                if (prop in previousState) {
                    return unref(previousState[prop]);
                }

                console.warn(
                    `[Options API Shim] Property "${prop}" not found in component state. ` +
                        `This may indicate accessing private/unexposed state.`,
                );

                return undefined;
            },
            set(_target: object, prop: string | symbol, value: unknown): boolean {
                if (typeof prop !== 'string') {
                    return false;
                }

                if (prop in localState) {
                    if (isRef(localState[prop])) {
                        (localState[prop] as Ref).value = value;
                        return true;
                    }
                    (localState as Record<string, unknown>)[prop] = value;
                    return true;
                }

                if (prop in previousState) {
                    if (isRef(previousState[prop])) {
                        (previousState[prop] as Ref).value = value;
                        return true;
                    }
                    console.error(`[Options API Shim] Cannot set property "${prop}" - property is not a ref or is readonly`);
                    return false;
                }

                if (Object.hasOwn(props, prop)) {
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
 * Converts Options API computed properties to Composition API computed refs
 */
function convertComputed(computedDefs: Record<string, ComputedDefinition>, thisProxy: object): Record<string, ComputedRef> {
    const converted: Record<string, ComputedRef> = {};

    Object.entries(computedDefs).forEach(
        ([
            name,
            computedDef,
        ]) => {
            if (typeof computedDef === 'function') {
                // Simple getter
                converted[name] = computed(() => computedDef.call(thisProxy));
            } else if (computedDef && typeof computedDef === 'object' && (computedDef.get || computedDef.set)) {
                // Getter/setter
                const getter = computedDef.get ? () => computedDef.get!.call(thisProxy) : undefined;
                const setter = computedDef.set ? (val: unknown) => computedDef.set!.call(thisProxy, val) : undefined;

                if (getter && setter) {
                    converted[name] = computed({
                        get: getter,
                        set: setter,
                    });
                } else if (getter) {
                    converted[name] = computed(getter);
                } else {
                    console.error(
                        `[Options-Composition-Shim] Computed property "${name}" has a setter but no getter. ` +
                            'A computed property must have at least a getter. The property will be skipped.',
                    );
                }
            }
        },
    );

    return converted;
}

/**
 * Converts Options API data() function to refs
 */
function convertData(dataFn: (() => Record<string, unknown>) | Record<string, unknown>): Record<string, Ref> {
    const data = typeof dataFn === 'function' ? dataFn() : dataFn;
    const converted: Record<string, Ref> = {};

    if (!data || typeof data !== 'object') {
        return converted;
    }

    Object.entries(data).forEach(
        ([
            key,
            value,
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
function registerSingleWatcher(source: () => unknown, handler: SingleWatchDefinition, thisProxy: object): void {
    if (typeof handler === 'function') {
        watch(source, (newVal: unknown, oldVal: unknown) => {
            handler.call(thisProxy, newVal, oldVal);
        });
    } else if (typeof handler === 'object' && handler.handler) {
        const options: WatchOptions = {};
        if (handler.immediate !== undefined) options.immediate = handler.immediate;
        if (handler.deep !== undefined) options.deep = handler.deep;
        if (handler.flush !== undefined) options.flush = handler.flush;

        watch(
            source,
            (newVal: unknown, oldVal: unknown) => {
                handler.handler.call(thisProxy, newVal, oldVal);
            },
            options,
        );
    } else if (typeof handler === 'string') {
        const methodName = handler;
        watch(source, (newVal: unknown, oldVal: unknown) => {
            const proxyAsState = thisProxy as ComponentState;
            if (proxyAsState[methodName] && typeof proxyAsState[methodName] === 'function') {
                (proxyAsState[methodName] as AnyFn)(newVal, oldVal);
            } else {
                console.error(
                    `[Options API Shim] Watch handler "${methodName}" is not a function or does not exist on the component.`,
                );
            }
        });
    }
}

/**
 * Sets up watchers for Options API watch configuration
 */
function setupWatchers(watchConfig: Record<string, WatchDefinition>, thisProxy: object): void {
    Object.entries(watchConfig).forEach(
        ([
            key,
            handler,
        ]) => {
            if (key.includes('.')) {
                console.warn(
                    `[Options API Shim] Dot-notation watch path "${key}" is not supported by the compatibility shim. ` +
                        `Please migrate your watcher to Composition API.`,
                );
                return;
            }

            const source = (): unknown => (thisProxy as ComponentState)[key];

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
function setupLifecycleHooks(hooks: Partial<Record<LifecycleHookName, LifecycleHookFn[]>>, thisProxy: object): void {
    const instance = getCurrentInstance();

    (Object.entries(hooks) as Array<[LifecycleHookName, LifecycleHookFn[] | undefined]>).forEach(
        ([
            hookName,
            handlers,
        ]) => {
            if (!handlers) {
                return;
            }

            const compositionHook = LIFECYCLE_HOOK_MAP[hookName];

            handlers.forEach((handler) => {
                if (compositionHook === null) {
                    handler.call(thisProxy);
                    return;
                }

                if (instance) {
                    compositionHook(() => {
                        handler.call(thisProxy);
                    });
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

    const extended = config as ExtendedComponentConfig;
    UNSUPPORTED_OPTIONS.forEach((key) => {
        if (extended[key]) {
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
