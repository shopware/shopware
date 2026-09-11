/** @sw-package framework */
import type { ComponentInternalInstance } from 'vue';

type Definition = { name?: string; __swLegacyNames?: string[]; extends?: Definition };

/** @private */
export function getLegacyComponentNames(instance: ComponentInternalInstance | null | undefined): string[] | undefined {
    let definition = instance?.type as Definition | undefined;
    if (definition?.__swLegacyNames) return definition.__swLegacyNames;
    const names: string[] = [];
    const visited = new Set<Definition>();
    while (definition && !visited.has(definition)) {
        visited.add(definition);
        if (definition.name) names.unshift(definition.name);
        definition = typeof definition.extends === 'object' ? definition.extends : undefined;
    }
    return names.length ? names : undefined;
}
