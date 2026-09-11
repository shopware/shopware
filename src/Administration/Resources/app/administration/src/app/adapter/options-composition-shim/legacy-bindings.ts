/** @sw-package framework */
import type { ComponentInternalInstance } from 'vue';
import type { ComponentState, LegacyOverrideOwner } from './types';

/**
 * Translate only explicitly retained legacy names. Native overrides still receive the original
 * public/private separation, while legacy this and $super see the names present before migration.
 * @private
 */
export function createLegacyBindingView(
    previousState: ComponentState,
    owner: LegacyOverrideOwner | undefined,
    instance: ComponentInternalInstance | null,
) {
    const aliases: Record<string, string> =
        instance && typeof instance.type !== 'function'
            ? ((instance.type.legacyOptionsBindings ?? {}) as Record<string, string>)
            : {};
    const legacyPrevious = { ...previousState };
    const previous = previousState as Record<string, unknown>;
    const privateState = previous._private as Record<string, unknown> | undefined;
    for (const [
        legacyName,
        binding,
    ] of Object.entries(aliases)) {
        legacyPrevious[legacyName] = previous[binding] ?? privateState?.[binding];
    }
    const legacyOwner = owner
        ? {
              ...owner,
              state: new Proxy(owner.state, {
                  get(target, key) {
                      return Reflect.get(target, typeof key === 'string' ? (aliases[key] ?? key) : key) as unknown;
                  },
                  set(target, key, value) {
                      return Reflect.set(target, typeof key === 'string' ? (aliases[key] ?? key) : key, value);
                  },
                  has(target, key) {
                      return Reflect.has(target, typeof key === 'string' ? (aliases[key] ?? key) : key);
                  },
              }),
          }
        : undefined;
    // Keep the layer's initialization flag live: it changes after the result is published.
    if (legacyOwner && owner) Object.defineProperty(legacyOwner, 'initializing', { get: () => owner.initializing });
    return { aliases, previous: legacyPrevious, owner: legacyOwner };
}
