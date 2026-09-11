/** @sw-package framework */
import { isRef, reactive, unref, type ComponentInternalInstance } from 'vue';
import { exposeNativeOptions } from './native-options-exposure';

type State = Record<string, unknown>;
type Owner = ComponentInternalInstance & { ctx: State };
type Bridge = {
    owner: Owner;
    original: State;
    names: Map<string, string>;
    legacyNames: Map<string, string>;
    declaredMembers: Set<string>;
    fallback: Map<string, PropertyDescriptor>;
    overlay: State;
    optionsStarted: boolean;
};
const bridges = new WeakMap<ComponentInternalInstance, Bridge>();

/**
 * Share SFC bindings with Vue's real Options instance. The proxy reads data and ctx directly:
 * setupState contains the bridge's own accessors, so reading it here would recurse.
 * @private
 */
export function createNativeOptionsState(instance: ComponentInternalInstance, original: State, publicKeys: string[]): State {
    const names = new Map<string, string>();
    for (const key of publicKeys) names.set(key, key);
    const aliases = (instance.type as { legacyOptionsBindings?: Record<string, string> }).legacyOptionsBindings ?? {};
    for (const [
        name,
        binding,
    ] of Object.entries(aliases))
        names.set(name, binding);
    const legacyNames = new Map<string, string>();
    for (const [
        name,
        binding,
    ] of names)
        legacyNames.set(binding, name);

    const bridge: Bridge = {
        owner: instance as Owner,
        original,
        names,
        legacyNames,
        declaredMembers: new Set(Object.keys((instance.proxy?.$options.legacyOptionsMembers ?? {}) as object)),
        fallback: new Map(),
        overlay: reactive({}),
        optionsStarted: false,
    };
    const state = createStateProxy(bridge);
    installSetupFallbacks(bridge, state);
    bridges.set(instance, bridge);
    return state;
}

function createStateProxy(bridge: Bridge): State {
    return new Proxy(reactive(bridge.original), {
        get(target, key, receiver) {
            return typeof key === 'string'
                ? readBinding(bridge, target, key)
                : (Reflect.get(target, key, receiver) as unknown);
        },
        set(target, key, value) {
            if (typeof key !== 'string') return Reflect.set(target, key, value);
            writeBinding(bridge, target, key, value);
            return true;
        },
        has(target, key) {
            return (
                Reflect.has(target, key) ||
                Reflect.has(bridge.overlay, key) ||
                (typeof key === 'string' &&
                    (Object.hasOwn(bridge.owner.data, key) ||
                        hasNativeContext(bridge, key) ||
                        Object.hasOwn(bridge.owner.props, key)))
            );
        },
        ownKeys(target) {
            const keys = new Set([
                ...Reflect.ownKeys(target),
                ...Object.keys(bridge.overlay),
                ...Object.keys(bridge.owner.data),
            ]);
            if (bridge.optionsStarted) {
                for (const key of Object.keys(bridge.owner.ctx)) {
                    if (!key.startsWith('$') && !key.startsWith('_')) keys.add(key);
                }
            }
            return [...keys];
        },
        getOwnPropertyDescriptor(target, key) {
            return Reflect.getOwnPropertyDescriptor(target, key) ?? { configurable: true, enumerable: true };
        },
    });
}

function readBinding(bridge: Bridge, source: State, key: string): unknown {
    if (Object.hasOwn(bridge.overlay, key)) return bridge.overlay[key];
    const name = bridge.legacyNames.get(key) ?? key;
    if (Object.hasOwn(bridge.owner.data, name)) return bridge.owner.data[name];
    if (hasNativeContext(bridge, name)) return bridge.owner.ctx[name];
    if (Object.hasOwn(bridge.owner.props, name)) return bridge.owner.props[name];
    // Setup has already run, but Options data and computed must remain unavailable until Vue installs them.
    // This also prevents an early read from caching a base computed before override data exists.
    if (bridge.declaredMembers.has(name)) return undefined;
    return unref(source[key]);
}

function writeBinding(bridge: Bridge, source: State, key: string, value: unknown): void {
    if (isRef(value) || typeof value === 'function' || Object.hasOwn(bridge.overlay, key)) {
        bridge.overlay[key] = value;
        return;
    }
    const name = bridge.legacyNames.get(key) ?? key;
    if (Object.hasOwn(bridge.owner.data, name)) {
        bridge.owner.data[name] = value;
        return;
    }
    if (hasNativeContext(bridge, name)) {
        bridge.owner.ctx[name] = value;
        return;
    }
    const current = source[key];
    if (isRef(current)) current.value = value;
    else source[key] = value;
}

function hasNativeContext(bridge: Bridge, key: string): boolean {
    if (!bridge.optionsStarted) return false;
    const descriptor = Object.getOwnPropertyDescriptor(bridge.owner.ctx, key);
    return !!descriptor && (!bridge.fallback.has(key) || descriptor.get !== bridge.fallback.get(key)?.get);
}

/** Context fallbacks expose base bindings until Vue installs a legacy method, computed or injection. */
function installSetupFallbacks(bridge: Bridge, state: State): void {
    const names = new Map<string, string>();
    for (const key of Object.keys(bridge.original)) names.set(key, key);
    for (const [
        name,
        binding,
    ] of bridge.names)
        names.set(name, binding);
    for (const [
        name,
        binding,
    ] of names) {
        // Native members need no temporary ctx entry: Vue could cache it before reactive data is installed.
        if (bridge.declaredMembers.has(name)) continue;
        const descriptor: PropertyDescriptor = {
            configurable: true,
            enumerable: true,
            get: () => state[binding],
            set: (value: unknown) => {
                // Production Vue installs bound Options methods by assignment instead of defineProperty.
                Object.defineProperty(bridge.owner.ctx, name, {
                    configurable: true,
                    enumerable: true,
                    writable: true,
                    value,
                });
            },
        };
        bridge.fallback.set(name, descriptor);
        Object.defineProperty(bridge.owner.ctx, name, descriptor);
    }
}

/** Vue merges this data factory with unchanged legacy data factories. @private */
export function baseOptionsData(instance: ComponentInternalInstance): State {
    const bridge = bridges.get(instance);
    if (!bridge) return {};
    const options = instance.proxy?.$options;
    const data: State = {};
    for (const [
        name,
        binding,
    ] of bridge.names) {
        if (typeof bridge.original[binding] === 'function') continue;
        if (Object.hasOwn((options?.computed ?? {}) as object, name)) continue;
        if (Object.hasOwn((options?.methods ?? {}) as object, name)) continue;
        data[name] = bridge.original[binding];
    }
    return data;
}

/** Read the unmodified SFC binding for a legacy $super call. @private */
export function readOriginalBinding(instance: ComponentInternalInstance, name: string): unknown {
    const bridge = bridges.get(instance);
    return bridge?.original[bridge.names.get(name) ?? name];
}

/** Enter Vue Options initialization after setup has been exposed on the instance. @private */
export function initializeNativeOptions(instance: ComponentInternalInstance): void {
    const bridge = bridges.get(instance);
    if (!bridge) return;
    // Development Vue adds ctx accessors after setup. Restore our fallbacks before Options applies,
    // so hasNativeContext only accepts fields subsequently installed by Vue's Options initialization.
    for (const [
        name,
        descriptor,
    ] of bridge.fallback)
        Object.defineProperty(bridge.owner.ctx, name, descriptor);
    bridge.optionsStarted = true;
    exposeNativeOptions(instance, bridge.names.keys(), bridge.fallback);
}
