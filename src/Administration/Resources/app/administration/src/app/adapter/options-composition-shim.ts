/**
 * @sw-package framework
 *
 * Converts Options API overrides (`Shopware.Component.override()`) of a component that uses the
 * composition extension system into composition overrides.
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
import type { Ref, ComputedRef, WatchOptions, SetupContext } from 'vue';
import AsyncComponentFactory from 'src/core/factory/async-component.factory';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';

type LifecycleHookFn = (...args: unknown[]) => void;
type AnyFn = (...args: unknown[]) => unknown;
type ComponentState = Record<string, unknown>;

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

type ExtendedComponentConfig = ComponentConfig & {
    [K in LifecycleHookName]?: LifecycleHookFn;
};

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyRecord = Record<string, any>;

/**
 * @private
 */
export type OverrideFn = (previousState: AnyRecord, props: AnyRecord, context?: SetupContext) => ComponentState;

/** `null`: the hook runs during setup, which replaces both `beforeCreate` and `created`. */
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

/** `extends` is included so that `checkUnsupportedFeatures()` can warn about it. */
const OPTION_KEYS = [
    'data',
    'methods',
    'computed',
    'watch',
    'mixins',
    'inject',
    'extends',
] as const;

const UNSUPPORTED_OPTIONS = [
    'components',
    'directives',
    'provide',
    'template',
    'extends',
    'inheritAttrs',
    'emits',
] as const;

interface MergedConfig {
    data?: () => Record<string, unknown>;
    computed: Record<string, ComputedDefinition>;
    methods: Record<string, AnyFn>;
    watch: Record<string, WatchDefinition>;
    inject?: InjectConfig;
    lifecycleHooks: Partial<Record<LifecycleHookName, LifecycleHookFn[]>>;
}

const convertedOverrides = new WeakSet<OverrideFn>();

const convertedOverridesByComponent = new Map<
    string,
    {
        sources: Array<ComponentConfig | null>;
        overrides: OverrideFn[];
    }
>();

/**
 * @private
 *
 * Returns true when an override config uses Options API patterns the shim has to convert.
 */
export function shouldActivateShim(overrideConfig: ComponentConfig): boolean {
    const extended = overrideConfig as ExtendedComponentConfig;
    const hasOptionKeys = OPTION_KEYS.some((key) => {
        const val: unknown = extended[key];
        return Array.isArray(val) ? val.length > 0 : !!val;
    });

    return hasOptionKeys || LIFECYCLE_HOOKS.some((hook) => !!extended[hook]);
}

/**
 * @private
 *
 * Returns the converted Options API overrides of a component in factory index order. They are converted
 * once per component name and reconverted only when the factory's override registry changes.
 */
export function getOptionsApiOverrides(componentName: string): OverrideFn[] {
    const sources = AsyncComponentFactory.getResolvedOverrideConfigs(componentName);
    const cached = convertedOverridesByComponent.get(componentName);

    if (
        cached &&
        cached.sources.length === sources.length &&
        cached.sources.every((source, index) => source === sources[index])
    ) {
        return cached.overrides;
    }

    const pendingCount = sources.filter((source) => source === null).length;
    if (pendingCount > 0 && process.env.NODE_ENV !== 'production') {
        console.warn(
            `[Options API Shim] ${pendingCount} override(s) of "${componentName}" are not resolved yet and are skipped. ` +
                'Build the component with Shopware.Component.build() before it is set up.',
        );
    }

    const overrides = sources
        .filter((source): source is ComponentConfig => source !== null && shouldActivateShim(source))
        .map((source) => convertOptionsApiOverrideToCompositionApi(componentName, source));

    convertedOverridesByComponent.set(componentName, { sources, overrides });

    return overrides;
}

/**
 * @private
 *
 * Converts one Options API override config into a composition override. The returned function has to run
 * inside the component's `setup()`, because it resolves `inject`, registers lifecycle hooks and watchers and
 * binds `this.$…` to the current instance.
 */
export function convertOptionsApiOverrideToCompositionApi(
    componentName: string,
    optionsConfig: ComponentConfig,
): OverrideFn {
    logDeprecationWarning(componentName);
    checkUnsupportedFeatures(componentName, optionsConfig);

    const mergedConfig = mergeMixins(optionsConfig);

    const override: OverrideFn = (previousState, props) => {
        const result: ComponentState = mergedConfig.data ? convertData(mergedConfig.data()) : {};
        const thisProxy = createThisProxy(
            previousState,
            props as ComponentState,
            result,
            resolveInject(mergedConfig.inject),
        );

        Object.assign(result, convertComputed(mergedConfig.computed, thisProxy));
        Object.assign(result, convertMethods(mergedConfig.methods, thisProxy));
        setupWatchers(mergedConfig.watch, thisProxy);
        setupLifecycleHooks(mergedConfig.lifecycleHooks, thisProxy);

        return result;
    };

    convertedOverrides.add(override);

    return override;
}

/**
 * @private
 *
 * Options API overrides may add bindings the component does not have, composition overrides should not.
 */
export function isConvertedOptionsApiOverride(override: OverrideFn): boolean {
    return convertedOverrides.has(override);
}

/** Depth-first, so the deepest ancestor comes first, like Vue's own mixin merge. */
function flattenMixins(mixin: ComponentConfig): ComponentConfig[] {
    const nested = mixin.mixins ? mixin.mixins.flatMap((m) => flattenMixins(m as ComponentConfig)) : [];
    return [...nested, mixin];
}

function resolveInject(injectConfig: InjectConfig): ComponentState {
    const resolved: ComponentState = {};

    if (!injectConfig) {
        return resolved;
    }

    if (Array.isArray(injectConfig)) {
        injectConfig.forEach((key: string) => {
            resolved[key] = vueInject(key);
        });

        return resolved;
    }

    Object.entries(injectConfig).forEach(([localKey, spec]) => {
        if (typeof spec === 'string') {
            resolved[localKey] = vueInject(spec);
        } else if (spec && typeof spec === 'object') {
            const specOptions = spec as { from?: string; default?: unknown };
            const from = specOptions.from ?? localKey;
            resolved[localKey] = Object.hasOwn(specOptions, 'default')
                ? vueInject(from, specOptions.default)
                : vueInject(from);
        } else {
            resolved[localKey] = vueInject(localKey);
        }
    });

    return resolved;
}

/** Existing (component-level) entries win on conflict, like Vue's merge strategy. */
function mergeInjectConfigs(existing: InjectConfig, incoming: InjectConfig): InjectConfig {
    const normalized: Record<string, unknown> = {};

    [
        existing,
        incoming,
    ].forEach((injectConfig) => {
        if (Array.isArray(injectConfig)) {
            injectConfig.forEach((key: string) => {
                if (!Object.hasOwn(normalized, key)) {
                    normalized[key] = key;
                }
            });
        } else if (injectConfig && typeof injectConfig === 'object') {
            Object.entries(injectConfig as Record<string, unknown>).forEach(([key, val]) => {
                if (!Object.hasOwn(normalized, key)) {
                    normalized[key] = val;
                }
            });
        }
    });

    return normalized as InjectConfig;
}

function toDataFactory(data: unknown): () => Record<string, unknown> {
    return typeof data === 'function'
        ? () => (data as () => Record<string, unknown>)()
        : () => data as Record<string, unknown>;
}

function mergeMixins(config: ComponentConfig): MergedConfig {
    const lifecycleHooks: MergedConfig['lifecycleHooks'] = {};
    const dataFactories: Array<() => Record<string, unknown>> = [];
    // Vue types methods/computed/watch as `any`, so they are cast once here.
    const merged: MergedConfig = {
        methods: { ...(config.methods as Record<string, AnyFn>) },
        computed: { ...(config.computed as Record<string, ComputedDefinition>) },
        watch: { ...(config.watch as Record<string, WatchDefinition>) },
        inject: config.inject,
        lifecycleHooks,
    };

    const collectHooks = (source: ComponentConfig) => {
        LIFECYCLE_HOOKS.forEach((hook) => {
            const hookFn = (source as ExtendedComponentConfig)[hook];
            if (hookFn) {
                (lifecycleHooks[hook] ??= []).push(hookFn);
            }
        });
    };

    (config.mixins ?? [])
        .flatMap((m) => flattenMixins(m as ComponentConfig))
        .forEach((mixin: ComponentConfig) => {
            collectHooks(mixin);

            if (mixin.data) {
                dataFactories.push(toDataFactory(mixin.data));
            }

            merged.methods = { ...(mixin.methods as Record<string, AnyFn>), ...merged.methods };
            merged.computed = { ...(mixin.computed as Record<string, ComputedDefinition>), ...merged.computed };
            merged.watch = { ...(mixin.watch as Record<string, WatchDefinition>), ...merged.watch };

            if (mixin.inject) {
                merged.inject = mergeInjectConfigs(merged.inject, mixin.inject);
            }
        });

    // The component's own data and hooks come last, so its data keys win and its hooks run after the mixins'.
    if (config.data) {
        dataFactories.push(toDataFactory(config.data));
    }
    collectHooks(config);

    if (dataFactories.length > 0) {
        merged.data = () => dataFactories.reduce<Record<string, unknown>>((acc, fn) => ({ ...acc, ...fn() }), {});
    }

    return merged;
}

function convertMethods(methods: Record<string, AnyFn>, thisProxy: object): ComponentState {
    const converted: ComponentState = {};

    Object.entries(methods).forEach(([name, method]) => {
        converted[name] = (...args: unknown[]) => method.apply(thisProxy, args);
    });

    return converted;
}

/**
 * `this` of the converted override: its own data/computed/methods first, then injections, props and the
 * previous state. `$`-prefixed keys resolve on the component instance that is being set up.
 */
function createThisProxy(
    previousState: ComponentState,
    props: ComponentState,
    localState: ComponentState,
    injectedValues: ComponentState,
): object {
    const instanceProxy = getCurrentInstance()?.proxy as Record<string, unknown> | null | undefined;

    return new Proxy(
        {},
        {
            get(_target: object, prop: string | symbol): unknown {
                if (typeof prop !== 'string') {
                    return undefined;
                }

                if (prop === '$super') {
                    return (methodName: string, ...args: unknown[]): unknown => {
                        const previous = previousState[methodName];

                        if (typeof previous === 'function') {
                            return (previous as AnyFn)(...args);
                        }

                        if (isRef(previous)) {
                            return previous.value;
                        }

                        throw new Error(
                            `$super: "${methodName}" not found in previous state. It must be a method (function) or a ref.`,
                        );
                    };
                }

                if (prop.startsWith('$')) {
                    return instanceProxy && prop in instanceProxy ? instanceProxy[prop] : undefined;
                }

                if (prop in localState) {
                    return unref(localState[prop]);
                }

                if (Object.hasOwn(injectedValues, prop)) {
                    return injectedValues[prop];
                }

                if (Object.hasOwn(props, prop)) {
                    return props[prop];
                }

                if (prop in previousState) {
                    return unref(previousState[prop]);
                }

                console.warn(
                    `[Options API Shim] Property "${prop}" not found in component state. ` +
                        'This may indicate accessing private/unexposed state.',
                );

                return undefined;
            },
            set(_target: object, prop: string | symbol, value: unknown): boolean {
                if (typeof prop !== 'string') {
                    return false;
                }

                if (prop in localState) {
                    if (isRef(localState[prop])) {
                        localState[prop].value = value;
                    } else {
                        localState[prop] = value;
                    }
                    return true;
                }

                if (prop in previousState) {
                    const previous = previousState[prop];
                    if (isRef(previous)) {
                        previous.value = value;
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

function convertComputed(computedDefs: Record<string, ComputedDefinition>, thisProxy: object): Record<string, ComputedRef> {
    const converted: Record<string, ComputedRef> = {};

    Object.entries(computedDefs).forEach(([name, computedDef]) => {
        if (typeof computedDef === 'function') {
            converted[name] = computed(() => computedDef.call(thisProxy));
            return;
        }

        if (!computedDef?.get) {
            if (computedDef?.set) {
                console.error(
                    `[Options-Composition-Shim] Computed property "${name}" has a setter but no getter. ` +
                        'A computed property must have at least a getter. The property will be skipped.',
                );
            }
            return;
        }

        const { get, set } = computedDef;
        converted[name] = set
            ? computed({ get: () => get.call(thisProxy), set: (val: unknown) => set.call(thisProxy, val) })
            : computed(() => get.call(thisProxy));
    });

    return converted;
}

function convertData(data: Record<string, unknown> | null | undefined): Record<string, Ref> {
    const converted: Record<string, Ref> = {};

    if (!data || typeof data !== 'object') {
        return converted;
    }

    Object.entries(data).forEach(([key, value]) => {
        converted[key] = ref(value);
    });

    return converted;
}

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
        watch(source, (newVal: unknown, oldVal: unknown) => {
            const method = (thisProxy as ComponentState)[handler];
            if (typeof method === 'function') {
                (method as AnyFn)(newVal, oldVal);
            } else {
                console.error(
                    `[Options API Shim] Watch handler "${handler}" is not a function or does not exist on the component.`,
                );
            }
        });
    }
}

function setupWatchers(watchConfig: Record<string, WatchDefinition>, thisProxy: object): void {
    Object.entries(watchConfig).forEach(([key, handler]) => {
        if (key.includes('.')) {
            console.warn(
                `[Options API Shim] Dot-notation watch path "${key}" is not supported by the compatibility shim. ` +
                    'Please migrate your watcher to Composition API.',
            );
            return;
        }

        const source = (): unknown => (thisProxy as ComponentState)[key];

        (Array.isArray(handler) ? handler : [handler]).forEach((h) => registerSingleWatcher(source, h, thisProxy));
    });
}

function setupLifecycleHooks(hooks: MergedConfig['lifecycleHooks'], thisProxy: object): void {
    (Object.entries(hooks) as Array<[LifecycleHookName, LifecycleHookFn[]]>).forEach(([hookName, handlers]) => {
        const compositionHook = LIFECYCLE_HOOK_MAP[hookName];

        handlers.forEach((handler) => {
            if (compositionHook === null) {
                handler.call(thisProxy);
                return;
            }

            compositionHook(() => {
                handler.call(thisProxy);
            });
        });
    });
}

function checkUnsupportedFeatures(componentName: string, config: ComponentConfig): void {
    if (typeof config.render === 'function') {
        console.error(
            '[Options API Shim] Custom render() functions are not supported by the compatibility shim. ' +
                `Component "${componentName}" will not work correctly. ` +
                'Please migrate to Composition API.',
        );
    }

    UNSUPPORTED_OPTIONS.forEach((key) => {
        if ((config as ExtendedComponentConfig)[key]) {
            console.warn(
                `[Options API Shim] "${key}" is not supported by the compatibility shim ` +
                    `in component "${componentName}". This option will be ignored.`,
            );
        }
    });
}

function logDeprecationWarning(componentName: string): void {
    console.warn(
        `[Deprecation Warning] Component "${componentName}" is being overridden with Options API patterns, ` +
            'but the target uses Composition API. A compatibility shim has been activated. ' +
            'This is a temporary solution and may have limitations. ' +
            'Please migrate your override to use Shopware.Component.overrideComponentSetup(). ' +
            'See: https://developer.shopware.com/docs/resources/references/core-reference/administration-reference/composition-api',
    );
}
