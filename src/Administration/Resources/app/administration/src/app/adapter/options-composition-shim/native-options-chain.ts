/** @sw-package framework */
import { isRef, unref, type ComponentInternalInstance } from 'vue';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import { readOriginalBinding } from './native-options-state';

type Method = (this: object, ...args: unknown[]) => unknown;
type Members = Map<string, Method>;
type Instance = { $: ComponentInternalInstance };

/**
 * Keep Options declarations in Vue's inheritance tree. Only Shopware's $super call needs an adapter;
 * ordinary reads, writes, watchers, injections, hooks and custom options remain Vue's responsibility.
 * @private
 */
export function nativeOptionsChain(configs: ComponentConfig[]): ComponentConfig[] {
    const members: Members = new Map();
    const ancestors = new Set<object>();
    function wrap(config: ComponentConfig): ComponentConfig {
        if (ancestors.has(config)) throw new Error('[Options API Bridge] Circular Options inheritance.');
        ancestors.add(config);
        const parent = config.extends;
        const result: ComponentConfig = { ...config };
        delete result.template;
        if (parent && typeof parent !== 'string') result.extends = wrap(parent);
        if (config.mixins)
            result.mixins = config.mixins.map((mixin) =>
                wrap(
                    typeof mixin === 'string'
                        ? (Shopware.Mixin.getByName(mixin as keyof MixinContainer) as ComponentConfig)
                        : (mixin as ComponentConfig),
                ),
            );
        const previous = new Map(members);
        const receivers = new WeakMap<object, object>();
        function receiver(vm: object): object {
            const instance = (vm as Instance).$;
            const host = instance.proxy!;
            let proxy = receivers.get(host);
            if (!proxy) {
                proxy = new Proxy(host, {
                    get(target, key) {
                        if (key !== '$super') return Reflect.get(target, key, target) as unknown;
                        return (name: string, ...args: unknown[]) => {
                            const predecessor = previous.get(name) ?? previous.get(`${name}.get`);
                            if (predecessor) return predecessor.apply(host, args);
                            const [
                                binding,
                                accessor,
                            ] = name.split('.');
                            const original = readOriginalBinding(instance, binding);
                            if (accessor === 'set' && isRef(original)) {
                                original.value = args[0];
                                return undefined;
                            }
                            if (typeof original === 'function') return (original as Method).apply(host, args);
                            if (original !== undefined) return unref(original);
                            throw new Error(`$super: "${name}" not found in the preceding component state.`);
                        };
                    },
                    set: (target, key, value) => Reflect.set(target, key, value, target),
                });
                receivers.set(host, proxy);
            }
            return proxy;
        }
        function method(name: string, fn: Method): Method {
            const wrapped: Method = function (...args) {
                return fn.apply(receiver(this), args);
            };
            members.set(name, wrapped);
            return wrapped;
        }
        if (config.methods)
            result.methods = Object.fromEntries(
                Object.entries(config.methods as Record<string, Method>).map(
                    ([
                        name,
                        fn,
                    ]) => [
                        name,
                        method(name, fn),
                    ],
                ),
            );
        if (config.computed)
            result.computed = Object.fromEntries<Method | { get?: Method; set?: Method }>(
                Object.entries(config.computed as Record<string, Method | { get?: Method; set?: Method }>).map(
                    ([
                        name,
                        definition,
                    ]) => {
                        members.delete(`${name}.get`);
                        members.delete(`${name}.set`);
                        if (typeof definition === 'function') {
                            const getter = method(name, definition);
                            members.set(`${name}.get`, getter);
                            return [
                                name,
                                getter,
                            ] as const;
                        }
                        const accessor = definition as { get?: Method; set?: Method };
                        const getter = accessor.get ? method(name, accessor.get) : undefined;
                        if (getter) members.set(`${name}.get`, getter);
                        return [
                            name,
                            {
                                ...accessor,
                                ...(getter ? { get: getter } : {}),
                                ...(accessor.set ? { set: method(`${name}.set`, accessor.set) } : {}),
                            },
                        ] as const;
                    },
                ),
            );
        ancestors.delete(config);
        return result;
    }
    return configs.map(wrap);
}
