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
    // The component it extends, as an object: Vue merges its members in.
    extends?: ComponentConfig;
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
 * A component has the members of the component it `extends` too.
 * Slots are left out, because Options API components don't declare them.
 *
 * @example
 * findMissingPublicNames({ methods: ['save'] }, { methods: {} }); // → ['methods:save']
 *
 * @private
 */
export function findMissingPublicNames(api: PublicComponentApi, component: ComponentConfig): string[] {
    const existing: Record<string, string[]> = {
        props: [],
        emits: [],
        computed: [],
        methods: [],
        blocks: [],
    };

    for (let config: ComponentConfig | undefined = component; config; config = config.extends) {
        existing.props.push(...namesOf(config.props));
        existing.emits.push(...namesOf(config.emits));
        existing.computed.push(...namesOf(config.computed));
        existing.methods.push(...namesOf(config.methods));
        existing.blocks.push(...blocksOf(config.template));
    }

    return Object.entries(existing).flatMap(([kind, names]) =>
        (api[kind as keyof PublicComponentApi] ?? [])
            .filter((name) => !names.includes(name))
            .map((name) => `${kind}:${name}`),
    );
}
