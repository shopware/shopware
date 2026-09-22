import type { ComponentInternalInstance } from '@vue/runtime-core';
import type { Reactive, Ref, ShallowUnwrapRef, ToRefs } from 'vue';
import { customRef, proxyRefs, reactive, toRef } from 'vue';

/**
 * @sw-package framework
 * @private
 *
 * Keeps script-setup override-local state available to component templates and block slots.
 *
 * The composition extension system stores override-file-owned `__swOverride` data outside Vue's public instance
 * internals, then exposes a proxy-compatible data scope for consumers such as `sw-block`.
 *
 * @example
 * const dataScope = createDataScope(reactiveSetupState);
 * const instance = getCurrentInstance();
 *
 * if (instance) setDataScopeForInstance(instance, dataScope);
 */

/**
 * @private
 *
 * Names the non-enumerable setup-state property that stores override-file-owned local data.
 *
 * Use this key for generated override return values so multiple override files can contribute isolated fields.
 *
 * @example
 * const __swSetupNamespace = Symbol('sw-thing.override');   // module root, one per override file
 * return { __swOverride: { [__swSetupNamespace]: { message: 'Hello' } } };
 */
export const OVERRIDE_LOCAL_STATE_KEY = '__swOverride' as const;

/**
 * @private
 *
 * Stores override-local data by override-file namespace.
 *
 * Each top-level key belongs to one override source file, which lets multiple files from the same plugin merge their
 * local state without replacing each other.
 *
 * @example
 * const state: OverrideLocalState = { 'plugin/first-override.ts': { headline: 'Draft' } };
 */
export type OverrideLocalState = Record<PropertyKey, Record<string, unknown>>;

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
 * Describes the `createExtendableSetup(...)` return value after the override-local state ref is attached.
 *
 * The `__swOverride` ref is intentionally non-enumerable so extension helpers can access it without exposing it as
 * ordinary public setup state.
 *
 * @example
 * const state: ExtendableSetupState<{ headline: string }> = createDataScope(reactiveSetupState);
 */
export type ExtendableSetupState<TState extends object> = ToRefs<Reactive<TState>> & {
    readonly [OVERRIDE_LOCAL_STATE_KEY]: Ref<Reactive<OverrideLocalState>>;
};

/**
 * Marks reactive setup state that already carries the hidden override-local state object.
 *
 * Use this shape at the data-scope boundary where `toRef(...)` needs the concrete `__swOverride` property.
 *
 * @example
 * createOverrideLocalStateRef(reactiveSetupState as ReactiveSetupStateWithOverrideLocalState<State>);
 */
type ReactiveSetupStateWithOverrideLocalState<TState extends object> = Reactive<TState> & {
    [OVERRIDE_LOCAL_STATE_KEY]: Reactive<OverrideLocalState>;
};

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
 * @private
 *
 * Creates the reactive container for override-file-owned local fields.
 *
 * Use this once per extendable setup result before registered override files are applied.
 *
 * @example
 * const overrideLocalState = createOverrideLocalState();
 */
export const createOverrideLocalState = (): Reactive<OverrideLocalState> => {
    return reactive({}) as Reactive<OverrideLocalState>;
};

/**
 * @private
 *
 * Attaches override-local state to setup state without making it enumerable.
 *
 * Use this before creating the data scope so templates can resolve `__swOverride` while normal state iteration stays
 * unchanged.
 *
 * @example
 * exposeOverrideLocalState(setupState, overrideLocalState);
 */
export const exposeOverrideLocalState = (target: object, overrideState: Reactive<OverrideLocalState>): void => {
    Object.defineProperty(target, OVERRIDE_LOCAL_STATE_KEY, {
        value: overrideState,
        enumerable: false,
    });
};

/**
 * Creates the ref used by Vue setup state for the hidden override-local state object.
 *
 * Use this only while building an `ExtendableSetupState`; callers should normally use `createDataScope(...)`.
 *
 * @example
 * const overrideRef = createOverrideLocalStateRef(reactiveSetupState);
 */
const createOverrideLocalStateRef = <TState extends object>(
    state: ReactiveSetupStateWithOverrideLocalState<TState>,
): Ref<Reactive<OverrideLocalState>> => {
    return toRef(state, OVERRIDE_LOCAL_STATE_KEY);
};

/**
 * @private
 *
 * Reads the hidden override-local state from reactive setup state.
 *
 * Use this when an override returns a `__swOverride` payload that should be merged into the existing namespace.
 *
 * @example
 * mergeOverrideState(getOverrideLocalState(reactiveSetupState), overrideResult.__swOverride);
 */
export const getOverrideLocalState = (state: object): OverrideLocalState => {
    return (state as Record<typeof OVERRIDE_LOCAL_STATE_KEY, OverrideLocalState>)[OVERRIDE_LOCAL_STATE_KEY];
};

/**
 * @private
 *
 * Narrows arbitrary override result keys to the override-local state key.
 *
 * Use this in override result loops before applying normal ref/computed/reactive merge behavior.
 *
 * @example
 * if (isOverrideLocalStateKey(key)) mergeOverrideState(target, value);
 */
export const isOverrideLocalStateKey = (key: string): key is typeof OVERRIDE_LOCAL_STATE_KEY => {
    return key === OVERRIDE_LOCAL_STATE_KEY;
};

/**
 * @private
 *
 * Merges override-file namespaces into the existing reactive state.
 *
 * Use this instead of assignment so later overrides add their file namespace without dropping earlier override fields.
 *
 * @example
 * mergeOverrideState(targetState, { 'plugin/second-override.ts': { message: 'Added' } });
 */
export const mergeOverrideState = (targetState: OverrideLocalState, overrideState: OverrideLocalState): void => {
    Object.assign(targetState, overrideState);
};

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
 * Use this after all setup state was made reactive so Vue ref unwrapping and the hidden `__swOverride` ref stay in sync.
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

    Object.defineProperty(state, OVERRIDE_LOCAL_STATE_KEY, {
        value: createOverrideLocalStateRef(reactiveSetupState as ReactiveSetupStateWithOverrideLocalState<TState>),
        enumerable: false,
        configurable: true,
    });

    return state;
};

/**
 * @private
 *
 * Associates a component instance with its proxy-compatible script-setup data scope.
 *
 * Use this once the extendable setup state is ready so block slot data can resolve override-local fields later.
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
