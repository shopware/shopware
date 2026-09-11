/**
 * @sw-package framework
 * @private
 */

import {
    watch,
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
    type WatchOptions,
} from 'vue';
import type {
    ComponentState,
    InjectConfig,
    SingleWatchDefinition,
    WatchDefinition,
    AnyFn,
    LifecycleHookName,
    LifecycleHookFn,
} from './types';

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

/** @private */
export const LIFECYCLE_HOOKS = Object.keys(LIFECYCLE_HOOK_MAP) as LifecycleHookName[];

const ALREADY_PASSED_WHEN_MOUNTED = new Set([
    'beforeCreate',
    'created',
    'beforeMount',
    'mounted',
]);

/** @private */
export function resolveInject(injectConfig: InjectConfig): ComponentState {
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

/** @private */
export function setupWatchers(watchConfig: Record<string, WatchDefinition>, thisProxy: object): void {
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

/** @private */
export function setupLifecycleHooks(hooks: Partial<Record<LifecycleHookName, LifecycleHookFn[]>>, thisProxy: object): void {
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
