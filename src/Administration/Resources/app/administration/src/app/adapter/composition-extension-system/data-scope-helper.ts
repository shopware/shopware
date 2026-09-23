import type { ComponentInternalInstance } from '@vue/runtime-core';
import type { Ref } from 'vue';
import { customRef } from 'vue';

/**
 * @sw-package framework
 * @private
 */

/**
 * @private
 */
export const OVERRIDE_LOCAL_STATE_KEY = '__swOverride' as const;

/**
 * @private
 *
 * Override-local bindings keyed by the namespace symbol of the override file that owns them.
 */
export type OverrideLocalState = Record<PropertyKey, Record<string, unknown>>;

/**
 * @private
 */
export type ScriptSetupDataScope = Record<string, unknown>;

const dataScopeByInstance = new WeakMap<ComponentInternalInstance, ScriptSetupDataScope>();

/**
 * @private
 *
 * Returns the reactive setup state of an extendable component, or `null` for any other component.
 */
export function getScriptSetupDataScope(instance: ComponentInternalInstance): ScriptSetupDataScope | null {
    return dataScopeByInstance.get(instance) ?? null;
}

/**
 * @private
 */
export function setScriptSetupDataScope(instance: ComponentInternalInstance, state: ScriptSetupDataScope): void {
    dataScopeByInstance.set(instance, state);
}

/**
 * @private
 *
 * Returns one writable ref per key of the reactive `state`, plus the non-enumerable `__swOverride` ref.
 *
 * Unlike `toRefs()`/`toRef()`, which read every key to detect existing refs, these refs read the state only
 * when accessed, so no computed is evaluated during `setup()` before the lifecycle hooks it may depend on.
 */
export function createLazyRefs(state: ScriptSetupDataScope): Record<string, Ref<unknown>> {
    const refs: Record<string, Ref<unknown>> = {};
    const createLazyRef = (key: string): Ref<unknown> =>
        customRef(() => ({
            get: () => state[key],
            set: (value: unknown) => {
                state[key] = value;
            },
        }));

    Object.keys(state).forEach((key) => {
        refs[key] = createLazyRef(key);
    });

    Object.defineProperty(refs, OVERRIDE_LOCAL_STATE_KEY, {
        value: createLazyRef(OVERRIDE_LOCAL_STATE_KEY),
        enumerable: false,
    });

    return refs;
}
