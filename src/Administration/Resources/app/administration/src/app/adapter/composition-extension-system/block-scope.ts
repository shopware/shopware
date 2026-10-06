import type { Slot } from 'vue';
import { isVNode } from 'vue';

/**
 * @sw-package framework
 * @private
 *
 * Lets an override template read per-instance setup bindings through one shared set of bindings.
 *
 * An override component is mounted once, so its template is compiled against one set of bindings: the
 * proxies `overrideComponentSetup()` returns. Its setup callback, though, runs once per base instance.
 * Each run stores its values in that instance's block scope, keyed by proxy. While `sw-block` renders
 * override content for an instance, that instance's block scope is active and every proxy acts as the
 * value stored for it there.
 *
 * @example
 * // override component, once
 * const { suffix } = Shopware.Component.overrideComponentSetup()('sw-example', () => {
 *     const suffix = ref('!');
 *     return { override: {}, local: { suffix } };
 * });
 *
 * // base instance A ran the callback, so its block scope holds A's own `suffix` ref
 * renderInBlockScope(dataScopeOfA, overrideSlot); // `{{ suffix }}` reads A's ref
 */

/**
 * @private
 *
 * One base instance's override values, keyed by the binding proxy each value stands behind.
 */
export type BlockScope = WeakMap<object, unknown>;

type Callback = (...args: unknown[]) => unknown;

let activeBlockScope: BlockScope | null = null;

const blockScopeByDataScope = new WeakMap<object, BlockScope>();

/**
 * @private
 *
 * Returns the block scope of the base instance behind `dataScope`, creating it on first use.
 *
 * `dataScope` is the object `sw-block` receives as its `data` prop - the script-setup data scope of a
 * migrated component, or the instance proxy of a Twig component - so the block can find it while
 * rendering.
 *
 * @example
 * const blockScope = getBlockScope(instance.proxy);
 */
export function getBlockScope(dataScope: object): BlockScope {
    const existing = blockScopeByDataScope.get(dataScope);

    if (existing) {
        return existing;
    }

    const blockScope: BlockScope = new WeakMap();
    blockScopeByDataScope.set(dataScope, blockScope);

    return blockScope;
}

/**
 * @private
 *
 * Makes `blockScope` the block scope of the base instance behind `dataScope`.
 *
 * Use this when the overrides run before the data scope exists, as in a migrated component's setup.
 *
 * @example
 * attachBlockScope(getScriptSetupDataScope(instance), blockScope);
 */
export function attachBlockScope(dataScope: object, blockScope: BlockScope): void {
    blockScopeByDataScope.set(dataScope, blockScope);
}

/**
 * Runs `callback` with `blockScope` as the scope binding proxies resolve against.
 */
function runInBlockScope<TResult>(blockScope: BlockScope, callback: () => TResult): TResult {
    const previousBlockScope = activeBlockScope;
    activeBlockScope = blockScope;

    try {
        return callback();
    } finally {
        activeBlockScope = previousBlockScope;
    }
}

/**
 * Creates the proxy one override binding is read through.
 *
 * Every operation is forwarded to the value the active block scope holds for this proxy, so it acts as
 * a ref for a ref, is callable for a function, and reads like the object for an object. The target is a
 * function only so that calls can be forwarded.
 *
 * Outside of a block it acts as an empty ref: Vue's setup-state unwrapping then routes a late write to
 * the `set` trap, instead of replacing the proxy in the override component's setup state.
 */
function createBindingProxy(name: string): object {
    // The traps look the proxy up by its own identity, which only exists once the Proxy is constructed.
    const self: { proxy?: object } = {};
    const resolve = (): Record<PropertyKey, unknown> | undefined =>
        (self.proxy ? activeBlockScope?.get(self.proxy) : undefined) as Record<PropertyKey, unknown> | undefined;

    self.proxy = new Proxy(() => {}, {
        get: (_target, key) => {
            const value = resolve();

            if (value === undefined || value === null) {
                return key === '__v_isRef' ? true : undefined;
            }

            return Reflect.get(value, key, value);
        },
        set: (_target, key, newValue) => {
            const value = resolve();

            if (value === undefined || value === null) {
                console.warn(`[sw-block] "${name}" was written outside of the block it belongs to.`);
                return true;
            }

            return Reflect.set(value, key, newValue, value);
        },
        has: (_target, key) => {
            const value = resolve();

            return typeof value === 'object' && value !== null ? Reflect.has(value, key) : false;
        },
        apply: (_target, thisArgument, args) => {
            return Reflect.apply(resolve() as unknown as Callback, thisArgument, args);
        },
    });

    return self.proxy;
}

/**
 * @private
 *
 * Creates the binding proxies of one override file, one per name, on first read.
 *
 * Destructuring the result creates exactly the proxies the template needs, so the caller never lists
 * the names.
 *
 * @example
 * const { suffix, headline } = createOverrideBindings();
 */
export function createOverrideBindings(): Record<string, object> {
    const bindings = new Map<string, object>();

    return new Proxy({} as Record<string, object>, {
        get: (_target, key) => {
            if (typeof key !== 'string') {
                return undefined;
            }

            if (!bindings.has(key)) {
                bindings.set(key, createBindingProxy(key));
            }

            return bindings.get(key);
        },
    });
}

/**
 * @private
 *
 * Stores one callback run's values in a block scope, each behind the proxy of its name.
 *
 * @example
 * hydrateOverrideBindings(blockScope, bindings, { suffix, headline });
 */
export function hydrateOverrideBindings(
    blockScope: BlockScope,
    bindings: Record<string, object>,
    values: Record<string, unknown>,
): void {
    Object.keys(values).forEach((name) => {
        blockScope.set(bindings[name], values[name]);
    });
}

/**
 * Compiled slots carry flags that Vue reads off the function itself: `_n` marks it as already bound to
 * its owner, `_c` as compiled, and `_d` toggles block tracking from the outside.
 */
type CompiledSlot = Slot & { _n?: boolean; _c?: boolean; _d?: boolean; _ctx?: unknown };

const boundCallbacks = new WeakMap<Callback, WeakMap<BlockScope, Callback>>();

/**
 * Wraps a callback the rendered content keeps for later, so it runs in the scope it was rendered for.
 *
 * Cached per callback and scope: a setup function passed as a prop keeps its identity across renders,
 * so the child does not see a changed prop on every block render.
 */
function bindCallback(callback: Callback, blockScope: BlockScope): Callback {
    const byScope = boundCallbacks.get(callback) ?? new WeakMap<BlockScope, Callback>();
    boundCallbacks.set(callback, byScope);

    const cached = byScope.get(blockScope);

    if (cached) {
        return cached;
    }

    const bound: Callback = (...args) => runInBlockScope(blockScope, () => callback(...args));
    byScope.set(blockScope, bound);

    return bound;
}

/**
 * Wraps a child component's slot: the child calls it during its own render, long after `sw-block`
 * returned, so both the call and the content it returns need the scope again.
 */
function bindSlot(slot: CompiledSlot, blockScope: BlockScope): CompiledSlot {
    const boundSlot: CompiledSlot = (...args: unknown[]) => {
        const nodes = runInBlockScope(blockScope, () => slot(...args));
        bindToBlockScope(nodes, blockScope);

        return nodes;
    };

    boundSlot._n = slot._n;
    boundSlot._c = slot._c;
    boundSlot._ctx = slot._ctx;
    Object.defineProperty(boundSlot, '_d', {
        get: () => slot._d,
        set: (value: boolean) => {
            slot._d = value;
        },
    });

    return boundSlot;
}

/**
 * Re-binds every deferred callback in a rendered vnode tree to `blockScope`, in place.
 */
function bindToBlockScope(nodes: unknown, blockScope: BlockScope): void {
    if (Array.isArray(nodes)) {
        nodes.forEach((node) => bindToBlockScope(node, blockScope));
        return;
    }

    if (!isVNode(nodes)) {
        return;
    }

    const props = nodes.props as Record<string, unknown> | null;

    Object.keys(props ?? {}).forEach((key) => {
        const value = props![key];

        // Every function prop, not only `on*` handlers: a child may call a formatter or filter later.
        if (typeof value === 'function') {
            props![key] = bindCallback(value as Callback, blockScope);
        } else if (/^on[A-Z]/.test(key) && Array.isArray(value)) {
            props![key] = value.map((handler: Callback) => bindCallback(handler, blockScope));
        }
    });

    const children = nodes.children as unknown;

    if (Array.isArray(children)) {
        bindToBlockScope(children, blockScope);
        return;
    }

    if (children && typeof children === 'object') {
        const slots = children as Record<string, unknown>;

        Object.keys(slots).forEach((name) => {
            if (typeof slots[name] === 'function') {
                slots[name] = bindSlot(slots[name] as CompiledSlot, blockScope);
            }
        });
    }
}

/**
 * @private
 *
 * Renders one override slot for the base instance behind `dataScope`, including everything its content
 * runs later.
 *
 * Binding proxies read the active block scope, which only exists while this call runs. Event handlers,
 * `v-model` updates and the slots handed to child components run after it returned, so each of them is
 * re-bound to the block scope on the way out.
 *
 * @example
 * const nodes = renderInBlockScope(props.data, block);
 */
export function renderInBlockScope(dataScope: unknown, slot: Slot): ReturnType<Slot> {
    const data = dataScope as Record<string, unknown>;

    if (typeof dataScope !== 'object' || dataScope === null) {
        return slot(data);
    }

    const blockScope = getBlockScope(dataScope);
    const nodes = runInBlockScope(blockScope, () => slot(data));
    bindToBlockScope(nodes, blockScope);

    return nodes;
}
