/**
 * @sw-package framework
 * @private
 */

import type { ComponentApi } from './extract-component-api';

/**
 * How the component reaches the page, which decides what the mini-module's entry files say:
 * `register` and `extend` put it into the component registry by its name, `local` leaves it to the parent
 * that imports it and lists it in its `components`.
 */
export type Shape = { kind: 'register' } | { kind: 'extend'; base: string } | { kind: 'local' };

/** The component and module the entry files are written for. */
export type Target = { name: string; packageName: string; shape: Shape };

const formatList = (names: string[]) =>
    names.length === 0 ? '[]' : `[\n${names.map((name) => `            '${name}',`).join('\n')}\n        ]`;

/** A local component has no global name, so only what its parent uses can be public. */
const LOCAL_LISTS = [
    'props',
    'emits',
    'slots',
] as const;

const ALL_LISTS = [
    'props',
    'emits',
    'slots',
    'blocks',
    'computed',
    'methods',
] as const;

const describeLists = (shape: Shape) => {
    if (shape.kind === 'local') {
        return ` * The component has no global name: its parent imports it through private.ts and mounts it locally,
 * so Component.override cannot reach it. The lists hold what a parent uses. Shrink them only through the deprecation process.`;
    }

    const inherited = shape.kind === 'extend' ? ` and what it inherits from ${shape.base}` : '';
    return ` * The lists hold everything the component offered before it became a mini-module${inherited},
 * because Component.override could reach all of it. Shrink them only through the deprecation process.`;
};

/**
 * The index.ts of a component module: the `definePublicComponent()` declaration with every public member.
 *
 * @example
 * renderIndex({ name: 'sw-b', packageName: 'framework', shape: { kind: 'extend', base: 'sw-a' } }, api);
 * // → "… definePublicComponent({ name: 'sw-b', extends: 'sw-a', load: () => import('./src/sw-b'), … })"
 */
export function renderIndex({ name, packageName, shape }: Target, api: ComponentApi): string {
    const lists = (shape.kind === 'local' ? LOCAL_LISTS : ALL_LISTS)
        .filter((list) => api[list].length > 0)
        .map((list) => `        ${list}: ${formatList(api[list])},`)
        .join('\n');
    const base = shape.kind === 'extend' ? `\n    extends: '${shape.base}',` : '';

    return `import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package ${packageName}
 *
 * This file is the public API, also for extensions. The files in \`./src\` are private.
${describeLists(shape)}
 */
/** @public */
export default definePublicComponent({
    name: '${name}',${base}
    load: () => import('./src/${name}'),
    public: {
${lists}
    },
});
`;
}

/**
 * The register.ts of a component module: it registers or extends the component by its name,
 * except for a local one, and publishes the module for extensions.
 *
 * @example
 * renderRegister({ name: 'sw-b', packageName: 'framework', shape: { kind: 'extend', base: 'sw-a' } });
 * // → "… Shopware.Component.extend(Component); Shopware._private.publish('components', 'sw-b', Module);"
 */
export function renderRegister({ name, packageName, shape }: Target): string {
    const registration = {
        register: 'Shopware.Component.register(Component);\n',
        extend: 'Shopware.Component.extend(Component);\n',
        local: '',
    }[shape.kind];

    return `import ${registration ? 'Component, ' : ''}* as Module from './index';

/**
 * @sw-package ${packageName}
 */

${registration}Shopware._private.publish('components', '${name}', Module);
`;
}

/**
 * The global.d.ts of a component module, which types `Component.override()` on the component's name.
 * A local component has no name to override, so it gets none.
 */
export function renderGlobalTypes({ name, packageName, shape }: Target): string | null {
    if (shape.kind === 'local') {
        return null;
    }

    return `import type Component from './index';

/**
 * @sw-package ${packageName}
 */

declare global {
    interface PublicComponents {
        '${name}': typeof Component;
    }
}
`;
}

/**
 * The private.ts of a local component module: the loaded component, for the parent that mounts it locally.
 * Registered components get none, because other code reaches them by their name.
 */
export function renderPrivate({ name, packageName, shape }: Target): string | null {
    if (shape.kind !== 'local') {
        return null;
    }

    return `/**
 * @sw-package ${packageName}
 *
 * The loaded component, for the parent that mounts it locally instead of by a global name.
 */
/** @private */
export { default } from './src/${name}';
`;
}
