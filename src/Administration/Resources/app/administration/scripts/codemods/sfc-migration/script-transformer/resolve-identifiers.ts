/**
 * Names for the bindings the codemod generates in place of Options API instance
 * APIs: `this.$emit(…)` becomes `emit(…)`, `this.$slots` becomes `slots`, and so
 * on. The preferred name is used unless the component already declares it, in
 * which case the next candidate wins.
 *
 * The candidate lists are deliberately disjoint, so every name can be resolved
 * up front — before a single line is emitted — without two generated bindings
 * ever competing for the same name.
 */
const IDENTIFIER_CANDIDATES = {
    emit: [
        'emit',
        '$emit',
        'vueEmit',
    ],
    router: [
        'router',
        '$router',
        'vueRouter',
    ],
    route: [
        'route',
        '$route',
        'vueRoute',
    ],
    slots: [
        'slots',
        '$slots',
        'vueSlots',
    ],
    attrs: [
        'attrs',
        '$attrs',
        'vueAttrs',
    ],
    t: [
        't',
        '$t',
        'translate',
    ],
} as const;

export type ResolvedIdentifiers = Record<keyof typeof IDENTIFIER_CANDIDATES, string>;

function pickName(candidates: readonly string[], taken: Set<string>): string {
    const free = candidates.find((candidate) => !taken.has(candidate));

    if (free) {
        return free;
    }

    for (let suffix = 2; ; suffix += 1) {
        const candidate = `${candidates[0]}${suffix}`;

        if (!taken.has(candidate)) {
            return candidate;
        }
    }
}

export function resolveIdentifierNames(takenNames: Iterable<string>): ResolvedIdentifiers {
    const taken = new Set(takenNames);
    const resolved = {} as Record<string, string>;

    for (const [
        key,
        candidates,
    ] of Object.entries(IDENTIFIER_CANDIDATES)) {
        const name = pickName(candidates, taken);

        resolved[key] = name;
        taken.add(name);
    }

    return resolved as ResolvedIdentifiers;
}
