/** @sw-package framework */
import type { ComponentConfig } from './async-component.factory';

type Loader = (name: string) => Promise<ComponentConfig | boolean>;

/** @private */
export function hasNamedInheritance(config: ComponentConfig, visited = new Set<object>()): boolean {
    if (visited.has(config)) return false;
    visited.add(config);
    return (
        typeof config.extends === 'string' ||
        (typeof config.extends === 'object' && hasNamedInheritance(config.extends, visited)) ||
        (config.mixins ?? []).some((mixin) => hasNamedInheritance(mixin as ComponentConfig, visited))
    );
}

/**
 * Resolve registered ancestor names before caching a legacy Options tree. Each branch has its own
 * ancestry set: repeated mixins are valid, but an inheritance cycle must fail instead of deadlocking.
 * @private
 */
export async function resolveLegacyInheritance(
    config: ComponentConfig,
    load: Loader,
    ancestors = new Set<string | object>(),
): Promise<ComponentConfig> {
    if (ancestors.has(config)) throw new Error('[Options API Shim] Circular Options inheritance.');
    const next = new Set(ancestors).add(config);
    const resolved = { ...config };
    const parent = config.extends;
    if (typeof parent === 'string') {
        if (next.has(parent)) throw new Error(`[Options API Shim] Circular Options inheritance: "${parent}".`);
        const loaded = await load(parent);
        if (typeof loaded === 'boolean') throw new Error(`[Options API Shim] Cannot resolve ancestor "${parent}".`);
        resolved.extends = await resolveLegacyInheritance(loaded, load, new Set(next).add(parent));
    } else if (parent) {
        resolved.extends = await resolveLegacyInheritance(parent, load, next);
    }
    if (config.mixins)
        resolved.mixins = await Promise.all(
            config.mixins.map((mixin) => resolveLegacyInheritance(mixin as ComponentConfig, load, next)),
        );
    return resolved;
}
