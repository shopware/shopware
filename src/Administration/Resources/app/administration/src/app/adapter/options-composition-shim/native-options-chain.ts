/** @sw-package framework */
import { isRef, unref, type ComponentInternalInstance } from 'vue';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import { readOriginalBinding } from './native-options-state';

type Method = (this: object, ...args: unknown[]) => unknown;
type Members = Map<string, Method>;
type Instance = { $: ComponentInternalInstance };
type Computed = Method | { get?: Method; set?: Method };

/**
 * Retain the Vue Options inheritance tree. Only Shopware's $super call needs an adapter;
 * ordinary reads, writes, watchers, injections, hooks and custom options remain Vue's responsibility.
 * @private
 */
export function nativeOptionsChain(configs: ComponentConfig[]): ComponentConfig[] {
    const members: Members = new Map();
    const ancestors = new Set<object>();
    function wrap(config: ComponentConfig): ComponentConfig {
        if (ancestors.has(config)) throw new Error('[Options API Bridge] Circular Options inheritance.');
        ancestors.add(config);
        const result: ComponentConfig = { ...config };
        delete result.template;
        if (config.extends && typeof config.extends !== 'string') result.extends = wrap(config.extends);
        if (config.mixins) result.mixins = config.mixins.map((mixin) => wrap(resolveMixin(mixin)));
        Object.assign(result, wrapMembers(config, members));
        ancestors.delete(config);
        return result;
    }
    return configs.map(wrap);
}

function resolveMixin(mixin: unknown): ComponentConfig {
    return typeof mixin === 'string'
        ? (Shopware.Mixin.getByName(mixin as keyof MixinContainer) as ComponentConfig)
        : (mixin as ComponentConfig);
}

/** Each layer closes over its own predecessors; later registrations cannot change a $super target. */
function wrapMembers(config: ComponentConfig, members: Members): object {
    const receiver = createSuperReceiver(new Map(members));
    const wrap = (name: string, method: Method): Method => {
        const wrapped: Method = function (...args) {
            return method.apply(receiver(this), args);
        };
        members.set(name, wrapped);
        return wrapped;
    };
    const result: { methods?: Record<string, Method>; computed?: Record<string, Computed> } = {};
    if (config.methods) {
        result.methods = {};
        for (const [
            name,
            method,
        ] of Object.entries(config.methods as Record<string, Method>))
            result.methods[name] = wrap(name, method);
    }
    if (config.computed) {
        result.computed = {};
        for (const [
            name,
            definition,
        ] of Object.entries(config.computed as Record<string, Computed>)) {
            members.delete(`${name}.get`);
            members.delete(`${name}.set`);
            const originalGetter = typeof definition === 'function' ? definition : definition.get;
            const getter = originalGetter ? wrap(name, withComputedVm(originalGetter)) : undefined;
            if (getter) members.set(`${name}.get`, getter);
            result.computed[name] =
                typeof definition === 'function'
                    ? getter!
                    : {
                          ...definition,
                          ...(getter ? { get: getter } : {}),
                          ...(definition.set ? { set: wrap(`${name}.set`, definition.set) } : {}),
                      };
        }
    }
    return result;
}

/** Vue supplies vm to computed getters; $super must supply the same receiver as this. */
function withComputedVm(getter: Method): Method {
    return function (...args) {
        if (!args.length) args.push(this);
        else if (args[0] === (this as Instance).$.proxy) args[0] = this;
        return getter.apply(this, args);
    };
}

/** A stable receiver also preserves the preceding layer when a method awaits before calling $super. */
function createSuperReceiver(previous: Members): (vm: object) => object {
    const receivers = new WeakMap<object, object>();
    return (vm) => {
        const instance = (vm as Instance).$;
        const host = instance.proxy!;
        let receiver = receivers.get(host);
        if (!receiver) {
            receiver = new Proxy(host, {
                get(target, key) {
                    if (key !== '$super') return Reflect.get(target, key, target) as unknown;
                    return (name: string, ...args: unknown[]) => callSuper(previous, instance, name, args);
                },
                set: (target, key, value) => Reflect.set(target, key, value, target),
            });
            receivers.set(host, receiver);
        }
        return receiver;
    };
}

function callSuper(previous: Members, instance: ComponentInternalInstance, name: string, args: unknown[]): unknown {
    const predecessor = previous.get(name) ?? previous.get(`${name}.get`);
    if (predecessor) return predecessor.apply(instance.proxy!, args);
    const [
        binding,
        accessor,
    ] = name.split('.');
    const original = readOriginalBinding(instance, binding);
    if (accessor === 'set' && isRef(original)) {
        original.value = args[0];
        return undefined;
    }
    if (typeof original === 'function') return (original as Method).apply(instance.proxy!, args);
    if (original !== undefined) return unref(original);
    throw new Error(`$super: "${name}" not found in the preceding component state.`);
}
