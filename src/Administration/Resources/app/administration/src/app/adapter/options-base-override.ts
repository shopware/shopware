/**
 * @sw-package framework
 *
 * Lets a composition-API override replace state on an Options-API base component.
 *
 * `createExtendableSetup()` only applies registered `overrideComponentSetup()` overrides for
 * components that call it — i.e. native-setup (composition) components. A composition override that
 * targets a component still written in the Options API therefore never runs. This module closes that
 * gap without rewriting the base: an injected `setup()` (added by the component factory) calls
 * `applyCompositionOverridesToOptionsBase()`, which runs the registered overrides and returns ONLY the
 * keys they replace. Those keys land in Vue's setupState, which resolves ahead of `data`/`computed`,
 * so the override shadows exactly the replaced keys — the rest of the Options component is untouched.
 *
 * `previousState` reads the base's original values un-shadowed: `data` keys come from `instance.data`
 * (which setupState never shadows), computed/other keys fall back to the public proxy. A synchronous
 * `.value` read inside an override body (before the base is initialised) cannot resolve and warns in
 * development.
 *
 * @experimental stableVersion:v6.8.0 feature:ADMIN_COMPOSITION_API_EXTENSION_SYSTEM
 */

import { computed, getCurrentInstance, isReadonly, isRef, reactive, watch } from 'vue';
import { syncRef } from '@vueuse/core';
import { _overridesMap } from './composition-extension-system';

const isDev = process.env.NODE_ENV !== 'production';

// Dedupe dev warnings per component:key so a repeated stale/synchronous read warns once.
const warnedKeys = new Set<string>();

function warnOnce(key: string, message: string): void {
    if (!isDev || warnedKeys.has(key)) {
        return;
    }

    warnedKeys.add(key);
    console.warn(message);
}

type InstanceLike = {
    data?: Record<string, unknown>;
    props?: Record<string, unknown>;
    proxy?: Record<string, unknown> | null;
};

/**
 * Builds the `previousState` passed to one override callback.
 *
 * Each field is a computed reading the base's ORIGINAL value: `data` keys from `instance.data` (never
 * shadowed by setupState), everything else from the public proxy. Access is lazy so the value is read
 * at render time, after the Options state is initialised.
 */
function createOptionsPreviousState(instance: InstanceLike, componentName: string): Record<string, unknown> {
    const cache = new Map<PropertyKey, unknown>();

    return new Proxy(
        {},
        {
            get(_target, key: PropertyKey) {
                if (key === '_private') {
                    // Options bases have no composition-private state.
                    return {};
                }

                if (!cache.has(key)) {
                    cache.set(
                        key,
                        computed(() => {
                            const data = instance.data;

                            if (data && Object.prototype.hasOwnProperty.call(data, key)) {
                                return data[key as string];
                            }

                            const value = instance.proxy?.[key as string];

                            if (value === undefined && isDev) {
                                warnOnce(
                                    `${componentName}:previous:${String(key)}`,
                                    `[${componentName}] previousState.${String(key)} was read before the Options base ` +
                                        `was initialised (e.g. synchronously inside the override body). Read it inside a ` +
                                        `computed/watch instead, where it resolves after initialisation.`,
                                );
                            }

                            return value;
                        }),
                    );
                }

                return cache.get(key);
            },
        },
    ) as Record<string, unknown>;
}

/**
 * @private
 *
 * Runs the composition overrides registered for an Options base and returns the replaced keys.
 *
 * Returns null when no override targets the component, so the injected `setup()` can skip returning a
 * shadow object entirely.
 */
export function applyCompositionOverridesToOptionsBase(componentName: string): Record<string, unknown> | null {
    const overrides = _overridesMap[componentName];

    if (!overrides || overrides.length === 0) {
        return null;
    }

    const instance = getCurrentInstance() as InstanceLike | null;

    if (!instance) {
        return null;
    }

    const shadow = reactive<Record<string, unknown>>({});
    const applied = new Set<unknown>();
    const previousState = createOptionsPreviousState(instance, componentName);

    const applyOverrides = (): void => {
        overrides.forEach((override) => {
            if (applied.has(override)) {
                return;
            }

            applied.add(override);

            let result: Record<string, unknown>;

            try {
                result = override(previousState as never, (instance.props ?? {}) as never, {} as never) as Record<
                    string,
                    unknown
                >;
            } catch (error) {
                console.error(
                    `[${componentName}] A composition override could not be applied to this Options API base:`,
                    error,
                );
                return;
            }

            Object.keys(result).forEach((key) => {
                const value = result[key];
                const existing = shadow[key];

                if (isRef(value) && !isReadonly(value) && isRef(existing) && !isReadonly(existing)) {
                    // Chained writable ref replacement stays 2-way synced with the earlier one.
                    syncRef(value, existing as never);
                    return;
                }

                if (isReadonly(value) && isRef(value) && isDev) {
                    warnOnce(
                        `${componentName}:computed:${key}`,
                        `[${componentName}] Override replaced "${key}" with a computed value. The template updates, ` +
                            `but the Options base's own writes to "${key}" (this.${key} = …) will no longer take effect.`,
                    );
                }

                // Shadow the key: Vue's setupState resolves ahead of data/computed, and unwraps refs.
                shadow[key] = value;
            });
        });
    };

    watch(overrides, applyOverrides, { deep: true, immediate: true });

    return shadow;
}

/**
 * @private
 *
 * Test-only reset of the dev-warning dedupe cache.
 */
export function _resetOptionsBaseOverrideWarnings(): void {
    warnedKeys.clear();
}
