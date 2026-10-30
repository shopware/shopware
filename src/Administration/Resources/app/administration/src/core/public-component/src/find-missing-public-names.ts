import type { PublicComponentApi } from './public-component';

/**
 * @sw-package framework
 */

type ComponentConfig = {
    props?: Record<string, unknown> | string[];
    emits?: Record<string, unknown> | string[];
    computed?: Record<string, unknown>;
    methods?: Record<string, unknown>;
    template?: string;
};

function namesOf(option: Record<string, unknown> | string[] | undefined): string[] {
    if (!option) {
        return [];
    }

    return Array.isArray(option) ? option : Object.keys(option);
}

function blocksOf(template: string | undefined): string[] {
    return [...(template ?? '').matchAll(/{%\s*block\s+([\w-]+)\s*%}/g)].map(([, name]) => name);
}

/**
 * Returns every name the public API lists but the component doesn't have, as `kind:name`,
 * so the list can't promise something the component lost.
 * Slots are left out, because Options API components don't declare them.
 *
 * @example
 * findMissingPublicNames({ methods: ['save'] }, { methods: {} }); // → ['methods:save']
 *
 * @private
 */
export function findMissingPublicNames(api: PublicComponentApi, component: ComponentConfig): string[] {
    const existing: Record<string, string[]> = {
        props: namesOf(component.props),
        emits: namesOf(component.emits),
        computed: namesOf(component.computed),
        methods: namesOf(component.methods),
        blocks: blocksOf(component.template),
    };

    return Object.entries(existing).flatMap(([kind, names]) =>
        (api[kind as keyof PublicComponentApi] ?? [])
            .filter((name) => !names.includes(name))
            .map((name) => `${kind}:${name}`),
    );
}
