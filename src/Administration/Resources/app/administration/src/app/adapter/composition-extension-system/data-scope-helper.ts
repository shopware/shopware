import type { ComponentInternalInstance } from '@vue/runtime-core';
import type { Reactive, Ref, ShallowUnwrapRef, ToRefs } from 'vue';
import { customRef, proxyRefs } from 'vue';

/**
 * @sw-package framework
 * @private
 *
 * Keeps script-setup state available to component templates and block slots.
 *
 * The composition extension system stores the data scope outside Vue's public instance internals, then exposes it
 * proxy-compatible for consumers such as `sw-block`.
 *
 * @example
 * const dataScope = createDataScope(reactiveSetupState);
 * const instance = getCurrentInstance();
 *
 * if (instance) setDataScopeForInstance(instance, dataScope);
 */

/**
 * Represents the proxy-compatible scope exposed to `sw-block` slot data.
 *
 * The scope uses Vue's setup refs as its source, while callers read values through `proxyRefs(...)` semantics.
 *
 * @example
 * const dataScope = getScriptSetupDataScope(instance);
 */
type ScriptSetupDataScope = ShallowUnwrapRef<ToRefs<Reactive<object>>>;

/**
 * @private
 *
 * Describes the `createExtendableSetup(...)` return value: one ref per setup-state key.
 *
 * @example
 * const state: ExtendableSetupState<{ headline: string }> = createDataScope(reactiveSetupState);
 */
export type ExtendableSetupState<TState extends object> = ToRefs<Reactive<TState>>;

const scriptSetupDataScopeByInstance = new WeakMap<ComponentInternalInstance, ScriptSetupDataScope>();

/**
 * @private
 *
 * Reads the data scope registered for a script-setup component instance.
 *
 * Use this from block rendering code before falling back to Vue's public instance proxy.
 *
 * @example
 * getScriptSetupDataScope(instance) ?? instance.proxy;
 */
export function getScriptSetupDataScope(instance: ComponentInternalInstance): ScriptSetupDataScope | null {
    return scriptSetupDataScopeByInstance.get(instance) ?? null;
}

/**
 * Creates one writable ref for a property of the reactive setup state, without reading the property.
 *
 * Vue's `toRefs(...)`/`toRef(source, key)` read `source[key]` to check for an existing ref, which evaluates
 * every computed in the state during `setup()`, before any lifecycle hook has run. Reading `source[key]`
 * lazily keeps computeds unevaluated until first access, and the indirection through the reactive state
 * lets an override replace a key later while staying visible to the component's own bindings.
 *
 * @example
 * const headlineRef = createPropertyRef(reactiveSetupState, 'headline');
 */
const createPropertyRef = (source: Record<string, unknown>, key: string): Ref<unknown> => {
    return customRef(() => ({
        get: () => source[key],
        set: (value: unknown) => {
            source[key] = value;
        },
    }));
};

/**
 * @private
 *
 * Converts reactive setup state into the return shape expected from `createExtendableSetup(...)`.
 *
 * Use this after all setup state was made reactive, so every ref reads through to the current value of its key.
 *
 * @example
 * const dataScope = createDataScope(reactiveSetupState);
 */
export const createDataScope = <TState extends object>(
    reactiveSetupState: Reactive<TState>,
): ExtendableSetupState<TState> => {
    const source = reactiveSetupState as Record<string, unknown>;
    const state = {} as ExtendableSetupState<TState>;

    // Enumerating the reactive proxy never reads a value, so the keys are collected the same way
    // `toRefs(...)` collected them - lazily built refs just replace the eagerly read ones.
    for (const key in reactiveSetupState) {
        (state as Record<string, unknown>)[key] = createPropertyRef(source, key);
    }

    return state;
};

/**
 * @private
 *
 * Associates a component instance with its proxy-compatible script-setup data scope.
 *
 * Use this once the extendable setup state is ready so block slots can read it later.
 *
 * @example
 * setDataScopeForInstance(getCurrentInstance(), dataScope);
 */
export const setDataScopeForInstance = <TState extends object>(
    instance: ComponentInternalInstance,
    state: ExtendableSetupState<TState>,
): void => {
    scriptSetupDataScopeByInstance.set(instance, proxyRefs(state) as ScriptSetupDataScope);
};
