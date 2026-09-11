/** @sw-package framework */
import type { ComponentInternalInstance } from 'vue';

type State = Record<string, unknown>;

/**
 * Vue's generated expose object contains only SFC names. Legacy Options can add members later,
 * including in lifecycle hooks; parent refs and $parent must see those members too.
 * Keep private setup fallbacks hidden unless a legacy declaration replaces them.
 * @private
 */
export function exposeNativeOptions(
    instance: ComponentInternalInstance,
    legacyNames: Iterable<string>,
    setupFallbacks: Map<string, PropertyDescriptor>,
): void {
    if (!instance.exposed) return;
    const owner = instance as ComponentInternalInstance & { ctx: State };
    const aliases = new Set(legacyNames);
    const isLegacyMember = (key: PropertyKey): key is string => {
        if (typeof key !== 'string' || key.startsWith('$') || key.startsWith('_')) return false;
        if (aliases.has(key) || Object.hasOwn(owner.data, key)) return true;
        const descriptor = Object.getOwnPropertyDescriptor(owner.ctx, key);
        return !!descriptor && (!setupFallbacks.has(key) || descriptor.get !== setupFallbacks.get(key)?.get);
    };

    instance.exposed = new Proxy(instance.exposed, {
        get(target, key, receiver) {
            if (Reflect.has(target, key)) return Reflect.get(target, key, receiver) as unknown;
            return isLegacyMember(key) ? (owner.proxy as unknown as State)[key] : undefined;
        },
        set(target, key, value, receiver) {
            if (Reflect.has(target, key) || !isLegacyMember(key)) return Reflect.set(target, key, value, receiver);
            return Reflect.set(owner.proxy!, key, value);
        },
        has: (target, key) => Reflect.has(target, key) || isLegacyMember(key),
        ownKeys(target) {
            return [
                ...new Set([
                    ...Reflect.ownKeys(target),
                    ...[
                        ...aliases,
                        ...Object.keys(owner.data),
                        ...Object.keys(owner.ctx),
                    ].filter(isLegacyMember),
                ]),
            ];
        },
        getOwnPropertyDescriptor(target, key) {
            return (
                Reflect.getOwnPropertyDescriptor(target, key) ??
                (isLegacyMember(key) ? { configurable: true, enumerable: true, writable: true } : undefined)
            );
        },
    });
}
