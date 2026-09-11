/** @sw-package framework */
import { isRef, reactive, unref, type ComponentInternalInstance } from 'vue';

type State = Record<string, unknown>;
type Owner = ComponentInternalInstance & { ctx: State };
type Bridge = {
    original: State;
    state: State;
    names: Map<string, string>;
    fallback: Map<string, PropertyDescriptor>;
    initialize: () => void;
};
const bridges = new WeakMap<ComponentInternalInstance, Bridge>();

/**
 * Share the SFC's bindings with Vue's real Options instance. Reads deliberately skip setupState:
 * setupState contains the accessors returned by this bridge and reading it would recurse.
 * @private
 */
export function createNativeOptionsState(instance: ComponentInternalInstance, original: State, publicKeys: string[]): State {
    const owner = instance as Owner;
    const aliases = (instance.type as { legacyOptionsBindings?: Record<string, string> }).legacyOptionsBindings ?? {};
    const names = new Map(
        publicKeys.map((key) => [
            key,
            key,
        ]),
    );
    Object.entries(aliases).forEach(
        ([
            name,
            binding,
        ]) => names.set(name, binding),
    );
    const legacyNames = new Map(
        [...names].map(
            ([
                name,
                binding,
            ]) => [
                binding,
                name,
            ],
        ),
    );
    const fallback = new Map<string, PropertyDescriptor>();
    const overlay = reactive({}) as State;
    let optionsStarted = false;
    const hasNativeContext = (key: string) => {
        if (!optionsStarted) return false;
        const descriptor = Object.getOwnPropertyDescriptor(owner.ctx, key);
        return descriptor && (!fallback.has(key) || descriptor.get !== fallback.get(key)?.get);
    };
    const state = new Proxy(reactive(original), {
        get(target, key, receiver) {
            if (typeof key !== 'string') return Reflect.get(target, key, receiver) as unknown;
            if (Object.hasOwn(overlay, key)) return overlay[key];
            const name = legacyNames.get(key) ?? key;
            if (Object.hasOwn(owner.data, name)) return owner.data[name];
            if (hasNativeContext(name)) return owner.ctx[name];
            if (Object.hasOwn(owner.props, name)) return owner.props[name];
            return unref(target[key]);
        },
        set(target, key, value) {
            if (typeof key !== 'string') return Reflect.set(target, key, value);
            if (isRef(value) || typeof value === 'function') {
                overlay[key] = value;
                return true;
            }
            if (Object.hasOwn(overlay, key)) {
                overlay[key] = value;
                return true;
            }
            const name = legacyNames.get(key) ?? key;
            if (Object.hasOwn(owner.data, name)) {
                owner.data[name] = value;
                return true;
            }
            if (hasNativeContext(name)) {
                owner.ctx[name] = value;
                return true;
            }
            const current = target[key];
            if (isRef(current)) current.value = value;
            else target[key] = value;
            return true;
        },
        has(target, key) {
            return (
                Reflect.has(target, key) ||
                Reflect.has(overlay, key) ||
                (typeof key === 'string' &&
                    (Object.hasOwn(owner.data, key) || hasNativeContext(key) || Object.hasOwn(owner.props, key)))
            );
        },
        ownKeys(target) {
            return [
                ...new Set([
                    ...Reflect.ownKeys(target),
                    ...Object.keys(overlay),
                    ...Object.keys(owner.data),
                    ...(optionsStarted
                        ? Object.keys(owner.ctx).filter((key) => !key.startsWith('$') && !key.startsWith('_'))
                        : []),
                ]),
            ];
        },
        getOwnPropertyDescriptor(target, key) {
            return Reflect.getOwnPropertyDescriptor(target, key) ?? { configurable: true, enumerable: true };
        },
    });
    for (const [
        name,
        binding,
    ] of new Map([
        ...Object.keys(original).map(
            (key) =>
                [
                    key,
                    key,
                ] as const,
        ),
        ...names,
    ])) {
        const descriptor: PropertyDescriptor = {
            configurable: true,
            enumerable: true,
            get: () => state[binding],
            set: (value: unknown) => {
                // Vue installs bound methods on ctx during Options initialization (assignment in production).
                Object.defineProperty(owner.ctx, name, { configurable: true, enumerable: true, writable: true, value });
            },
        };
        fallback.set(name, descriptor);
        Object.defineProperty(owner.ctx, name, descriptor);
    }
    bridges.set(instance, {
        original,
        state,
        names,
        fallback,
        initialize: () => {
            // Vue development builds expose setup accessors on ctx after setup returns. Replace those
            // before Options initialization so ctx only supplies values installed by Vue itself.
            for (const [
                name,
                descriptor,
            ] of fallback)
                Object.defineProperty(owner.ctx, name, descriptor);
            optionsStarted = true;
        },
    });
    return state;
}

/** Vue merges this data factory with the unchanged legacy data factories. @private */
export function baseOptionsData(instance: ComponentInternalInstance): State {
    const bridge = bridges.get(instance);
    if (!bridge) return {};
    const options = instance.proxy?.$options;
    return Object.fromEntries(
        [...bridge.names]
            .filter(
                ([
                    name,
                    binding,
                ]) =>
                    typeof bridge.original[binding] !== 'function' &&
                    !Object.hasOwn((options?.computed ?? {}) as object, name) &&
                    !Object.hasOwn((options?.methods ?? {}) as object, name),
            )
            .map(
                ([
                    name,
                    binding,
                ]) => [
                    name,
                    bridge.original[binding],
                ],
            ),
    );
}

/** Read the unmodified SFC binding for a legacy $super call. @private */
export function readOriginalBinding(instance: ComponentInternalInstance, name: string): unknown {
    const bridge = bridges.get(instance);
    return bridge?.original[bridge.names.get(name) ?? name];
}

/** Enter Vue Options initialization after setup has been exposed on the instance. @private */
export function initializeNativeOptions(instance: ComponentInternalInstance): void {
    bridges.get(instance)?.initialize();
}
