/**
 * @sw-package framework
 * @private
 */

import { getCurrentInstance, isRef, unref, type Ref } from 'vue';
import type { ComponentState, AnyFn } from './types';

/** @private */
export function createThisProxy<COMPONENT_NAME extends keyof ComponentPublicApiMapping & string = string>(
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
