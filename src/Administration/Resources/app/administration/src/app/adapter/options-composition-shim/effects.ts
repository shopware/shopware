/**
 * @sw-package framework
 * @private
 *
 * Registers legacy effects on their owning Vue instance, including overrides added after setup.
 */
import {
    watch,
    handleError,
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
    onRenderTracked,
    onRenderTriggered,
    onServerPrefetch,
    type ComponentInternalInstance,
    type WatchOptions,
    type WatchCallback,
} from 'vue';
import type {
    ComponentState,
    InjectConfig,
    SingleWatchDefinition,
    WatchDefinition,
    LifecycleHookName,
    LifecycleHookFn,
} from './types';

type ProvidingInstance = ComponentInternalInstance & { provides: Record<PropertyKey, unknown> };

function getProviders(instance: ComponentInternalInstance | null): Record<PropertyKey, unknown> | undefined {
    return (instance as ProvidingInstance | null)?.provides;
}

const lifecycleRegistrars = {
    beforeCreate: null,
    created: null,
    beforeMount: onBeforeMount,
    mounted: onMounted,
    beforeUpdate: onBeforeUpdate,
    updated: onUpdated,
    beforeUnmount: onBeforeUnmount,
    unmounted: onUnmounted,
    beforeDestroy: onBeforeUnmount,
    destroyed: onUnmounted,
    activated: onActivated,
    deactivated: onDeactivated,
    errorCaptured: onErrorCaptured,
    renderTracked: onRenderTracked,
    renderTriggered: onRenderTriggered,
    serverPrefetch: onServerPrefetch,
};

/** @private */
export const LIFECYCLE_HOOKS = Object.keys(lifecycleRegistrars) as LifecycleHookName[];

/** @private */
export function resolveInject(
    config: InjectConfig | undefined,
    owner: ComponentInternalInstance | null = getCurrentInstance(),
    receiver: object = {},
): ComponentState {
    const resolved: ComponentState = {};
    const providers = getProviders(owner?.parent ?? null) ?? owner?.appContext.provides ?? {};
    const entries = Array.isArray(config)
        ? config.map(
              (key) =>
                  [
                      key,
                      key,
                  ] as const,
          )
        : Object.entries(config ?? {});
    for (const [
        localKey,
        definition,
    ] of entries) {
        const spec =
            definition && typeof definition === 'object' ? (definition as { from?: PropertyKey; default?: unknown }) : null;
        const from =
            spec?.from ?? (typeof definition === 'string' || typeof definition === 'symbol' ? definition : localKey);
        if (from in providers) {
            resolved[localKey] = providers[from as string] as unknown;
        } else if (spec && Object.hasOwn(spec, 'default')) {
            resolved[localKey] =
                typeof spec.default === 'function'
                    ? (spec.default as (this: object) => unknown).call(receiver)
                    : spec.default;
        } else {
            console.warn(`[Options API Shim] Injection "${String(from)}" not found.`);
            resolved[localKey] = undefined;
        }
    }
    return resolved;
}

/** @private */
export function setupProvide(config: unknown, receiver: object, owner: ComponentInternalInstance | null): void {
    if (!config || !owner) return;
    const provided = typeof config === 'function' ? (config as (this: object) => object).call(receiver) : (config as object);
    const parentProviders = getProviders(owner.parent) ?? owner.appContext.provides;
    const instance = owner as ProvidingInstance;
    if (instance.provides === parentProviders)
        instance.provides = Object.create(parentProviders) as Record<PropertyKey, unknown>;
    for (const key of Reflect.ownKeys(provided)) {
        instance.provides[key] = Reflect.get(provided, key) as unknown;
    }
}

/** @private */
export function readWatchPath(receiver: object, path: string): unknown {
    return path
        .split('.')
        .reduce<unknown>(
            (value, key) => (value == null ? undefined : (Reflect.get(Object(value), key) as unknown)),
            receiver,
        );
}

/** @private */
export function registerWatcher(
    source: () => unknown,
    definition: SingleWatchDefinition,
    receiver: object,
): ReturnType<typeof watch> {
    const { handler, ...options } = typeof definition === 'object' ? definition : { handler: definition };
    return watch(
        source,
        (...args: Parameters<WatchCallback>) => {
            const callback: unknown = typeof handler === 'string' ? Reflect.get(receiver, handler) : handler;
            if (typeof callback !== 'function') {
                console.error(
                    `[Options API Shim] Watch handler "${String(handler)}" is not a function or does not exist on the component.`,
                );
                return;
            }
            return callback.apply(receiver, args) as unknown;
        },
        options as WatchOptions,
    );
}

/** @private */
export function setupWatchers(config: Record<string, WatchDefinition>, receiver: object): void {
    for (const [
        path,
        definition,
    ] of Object.entries(config)) {
        for (const handler of Array.isArray(definition) ? definition : [definition]) {
            registerWatcher(() => readWatchPath(receiver, path), handler, receiver);
        }
    }
}

/** @private */
export function setupLifecycleHooks(
    hooks: Partial<Record<LifecycleHookName, LifecycleHookFn[]>>,
    receiver: object,
    owner: ComponentInternalInstance | null = getCurrentInstance(),
): void {
    for (const name of LIFECYCLE_HOOKS) {
        for (const handler of hooks[name] ?? []) {
            const invoke = (...args: unknown[]) => handler.apply(receiver, args);
            const register = lifecycleRegistrars[name];
            if (!register || (owner?.isMounted && (name === 'beforeMount' || name === 'mounted'))) {
                const result = invoke();
                if (result instanceof Promise && owner) {
                    const phase = (name === 'beforeCreate' ? 'bc' : 'c') as Parameters<typeof handleError>[2];
                    void result.catch((error: unknown) => handleError(error, owner, phase));
                }
            } else if (owner) {
                register(invoke, owner);
            } else if (name === 'beforeMount' || name === 'mounted') {
                invoke();
            } else {
                console.warn(
                    `[Options API Shim] Lifecycle hook "${name}" could not be registered because no owning component instance is available.`,
                );
            }
        }
    }
}
