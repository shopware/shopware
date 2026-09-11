/**
 * @sw-package framework
 * @private
 */

import { getCurrentInstance, isRef, unref, type Ref } from 'vue';
import { readWatchPath, registerWatcher } from './effects';
import type { ComponentState, AnyFn, LegacyOverrideOwner, SingleWatchDefinition } from './types';

/** @private */
export function createThisProxy<COMPONENT_NAME extends keyof ComponentPublicApiMapping & string = string>(
    previousState: ComponentState<COMPONENT_NAME>,
    props: ComponentState<COMPONENT_NAME>,
    localState: ComponentState<COMPONENT_NAME>,
    injectedValues: ComponentState<COMPONENT_NAME> = {} as ComponentState<COMPONENT_NAME>,
    owner?: LegacyOverrideOwner,
): object {
    const componentInstance = owner?.instance ?? getCurrentInstance();

    const isVisible = (key: PropertyKey) =>
        key !== '_private' &&
        (key in previousState ||
            key in localState ||
            key in injectedValues ||
            !!(owner && typeof key === 'string' && !owner.privateKeys?.has(key) && key in owner.state));
    const proxy: object = new Proxy(
        {},
        {
            has(_target, prop) {
                return (
                    prop in localState ||
                    prop in previousState ||
                    prop in props ||
                    prop in injectedValues ||
                    !!(owner && isVisible(prop) && prop in owner.state)
                );
            },
            ownKeys() {
                return [
                    ...new Set([
                        ...Reflect.ownKeys(previousState),
                        ...Reflect.ownKeys(props),
                        ...Reflect.ownKeys(localState),
                        ...Reflect.ownKeys(injectedValues),
                    ]),
                ];
            },
            getOwnPropertyDescriptor(_target, prop) {
                if (!(prop in proxy)) return undefined;
                return {
                    configurable: true,
                    enumerable: true,
                    get: (): unknown => Reflect.get(proxy, prop) as unknown,
                    set: (value: unknown) => {
                        Reflect.set(proxy, prop, value);
                    },
                };
            },
            get(_target: object, prop: string | symbol): unknown {
                if (typeof prop !== 'string') {
                    return undefined;
                }

                if (prop === '$watch') {
                    return (source: string | AnyFn, handler: SingleWatchDefinition, options: object = {}) => {
                        const register = () =>
                            registerWatcher(
                                typeof source === 'string' ? () => readWatchPath(proxy, source) : () => source.call(proxy),
                                typeof handler === 'object' ? { ...options, ...handler } : { ...options, handler },
                                proxy,
                            );
                        return owner?.scope ? owner.scope.run(register) : register();
                    };
                }
                if (prop === '$data') return owner?.data ?? componentInstance?.data;
                if (prop === '$options' && owner?.options) return owner.options;

                // Handle $super calls
                if (prop === '$super') {
                    return (methodName: string, ...args: unknown[]): unknown => {
                        const [
                            name,
                            accessor,
                        ] = methodName.split('.');
                        const original: unknown = previousState[name];
                        if (isRef(original) && (accessor === 'get' || accessor === 'set')) {
                            if (accessor === 'get') return original.value;
                            original.value = args[0];
                            return undefined;
                        }
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
                    const vueProxy = componentInstance?.proxy as Record<string, unknown> | null | undefined;
                    if (vueProxy && prop in vueProxy) {
                        return vueProxy[prop];
                    }
                    return undefined;
                }

                if (owner && !owner.initializing && isVisible(prop) && prop in owner.state) return unref(owner.state[prop]);

                // Check local state first (data, computed, methods from override)
                if (prop in localState) {
                    return unref(localState[prop]);
                }

                // Check injected values (from Options API inject config)
                if (Object.hasOwn(injectedValues, prop)) {
                    return unref(injectedValues[prop]);
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

                if (Object.hasOwn(props, prop)) {
                    console.error(
                        `[Options API Shim] Cannot set property "${prop}" - it is a component prop and is read-only.`,
                    );
                    return false;
                }
                if (owner && !owner.initializing && isVisible(prop) && prop in owner.state) {
                    owner.state[prop] = value;
                    return true;
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

                (localState as Record<string, unknown>)[prop] = value;
                if (owner) owner.state[prop] = value;
                return true;
            },
        },
    );
    return proxy;
}
