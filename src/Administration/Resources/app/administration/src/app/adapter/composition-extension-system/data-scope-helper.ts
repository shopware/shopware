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

const ownerByDataScope = new WeakMap<object, ComponentInternalInstance>();

/** @private */
export function getDataScopeOwner(scope: object): ComponentInternalInstance | null {
    return ownerByDataScope.get(scope) ?? null;
}

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

    // Create refs lazily so fields introduced after setup stay visible without evaluating computeds.
    const liveState: ExtendableSetupState<TState> = new Proxy(state, {
        get(target, key, receiver) {
            if (typeof key === 'string' && key in source && !Object.hasOwn(target, key)) {
                Object.defineProperty(target, key, {
                    value: createPropertyRef(source, key),
                    configurable: true,
                    enumerable: true,
                });
            }
            return Reflect.get(target, key, receiver) as unknown;
        },
        has(target, key) {
            return key in target || key in source;
        },
        ownKeys(target) {
            return [
                ...new Set([
                    ...Reflect.ownKeys(target),
                    ...Object.keys(source),
                ]),
            ];
        },
        getOwnPropertyDescriptor(target, key): PropertyDescriptor | undefined {
            const existing = Reflect.getOwnPropertyDescriptor(target, key);
            if (existing) return existing;
            if (typeof key !== 'string' || !(key in source)) return undefined;
            return { configurable: true, enumerable: true, value: liveState[key as keyof typeof liveState] };
        },
    });

    Object.defineProperty(state, OVERRIDE_LOCAL_STATE_KEY, {
        value: createOverrideLocalStateRef(reactiveSetupState as ReactiveSetupStateWithOverrideLocalState<TState>),
        enumerable: false,
        configurable: true,
    });

    return liveState;
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
    const scope = proxyRefs(state) as ScriptSetupDataScope;
    scriptSetupDataScopeByInstance.set(instance, scope);
    ownerByDataScope.set(scope, instance);
    if (instance.proxy && !(instance.type as { __swNativeOptions?: boolean }).__swNativeOptions) {
        for (const key of Object.keys(state)) {
            if (key in instance.props) continue;
            Object.defineProperty(instance.proxy, key, {
                configurable: true,
                enumerable: true,
                get: () => Reflect.get(scope, key) as unknown,
                set: (value: unknown) => {
                    Reflect.set(scope, key, value);
                },
            });
        }
    }
};

/**
 * Overlay lexical loop/slot bindings without enumerating Vue's host proxy. Local bindings shadow
 * host fields and cannot be rebound, matching Vue's slot/loop alias rules; their objects remain writable.
 * @private
 */
export function createBlockDataScope(host: Record<PropertyKey, unknown>, locals: Record<string, unknown>): object {
    const scope = new Proxy(
        {},
        {
            get(_target, key) {
                return Object.hasOwn(locals, key) ? locals[key as string] : host[key];
            },
            has(_target, key) {
                return Object.hasOwn(locals, key) || key in host;
            },
            set(_target, key, value) {
                if (Object.hasOwn(locals, key)) {
                    console.warn(`[sw-block] Cannot reassign template-local binding "${String(key)}".`);
                    return false;
                }
                return Reflect.set(host, key, value);
            },
        },
    );
    const owner = getDataScopeOwner(host) ?? (host.$ as ComponentInternalInstance | undefined);
    if (owner) ownerByDataScope.set(scope, owner);
    return scope;
}
