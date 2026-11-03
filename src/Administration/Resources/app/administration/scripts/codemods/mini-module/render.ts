/**
 * @sw-package framework
 * @private
 */

import type { ComponentApi } from './extract-component-api';

/**
 * How the component reaches the page, which decides what the mini-module's entry files say:
 * `register` and `extend` put it into the component registry by its name, as a public module, or as a private one
 * when its registration is marked `@private`. `nested` leaves it to the parent that imports it and lists it in its
 * `components`, so it becomes a private module in the `src/` of the parent's module.
 */
export type Shape =
    | { kind: 'register'; isPrivate: boolean }
    | { kind: 'extend'; base: string; isPrivate: boolean }
    | { kind: 'nested'; parent: string };

/** Whether the module gets only a private.ts, no index.ts, and never reaches extensions. */
export const isPrivateShape = (shape: Shape) => shape.kind === 'nested' || shape.isPrivate;

/** The component and module the entry files are written for. */
export type Target = { name: string; packageName: string; shape: Shape };

const formatList = (names: string[]) =>
    names.length === 0 ? '[]' : `[\n${names.map((name) => `            '${name}',`).join('\n')}\n        ]`;

/** A nested component has no global name, so its lists hold only what a parent uses. */
const NESTED_LISTS = [
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
    const inherited = shape.kind === 'extend' ? ` and what it inherits from ${shape.base}` : '';
    return ` * The lists hold everything the component offered before it became a mini-module${inherited},
 * because Component.override could reach all of it. Shrink them only through the deprecation process.`;
};

/** The lists of a registered private component: what other Administration code could reach, which was everything. */
const describePrivateLists = (shape: Shape) =>
    shape.kind === 'nested'
        ? ` * The component for the module around it, which mounts it locally: it has no global name and never reaches extensions.
 * The lists hold what the parent uses. The files in \`./src\` are private to this module.`
        : ` * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module${shape.kind === 'extend' ? ` and what it inherits from ${shape.base}` : ''},
 * because Component.override inside the Administration could reach all of it. The files in \`./src\` are private to this module.`;

const renderLists = (lists: readonly (keyof ComponentApi)[], api: ComponentApi) =>
    lists
        .filter((list) => api[list].length > 0)
        .map((list) => `        ${list}: ${formatList(api[list])},`)
        .join('\n');

/**
 * The index.ts of a component module: the `definePublicComponent()` declaration with every public member.
 * A nested component gets none, because nothing outside its parent's module may use it.
 *
 * @example
 * renderIndex({ name: 'sw-b', packageName: 'framework', shape: { kind: 'extend', base: 'sw-a' } }, api);
 * // → "… definePublicComponent({ name: 'sw-b', extends: 'sw-a', load: () => import('./src/sw-b'), … })"
 */
export function renderIndex({ name, packageName, shape }: Target, api: ComponentApi): string | null {
    if (isPrivateShape(shape)) {
        return null;
    }

    const lists = renderLists(ALL_LISTS, api);
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
 * and publishes the module for extensions. A nested component has none, because it is neither.
 *
 * @example
 * renderRegister({ name: 'sw-b', packageName: 'framework', shape: { kind: 'extend', base: 'sw-a' } });
 * // → "… Shopware.Component.extend(Component); Shopware._private.publish('components', 'sw-b', Module);"
 */
export function renderRegister({ name, packageName, shape }: Target): string | null {
    if (shape.kind === 'nested') {
        return null;
    }

    if (shape.isPrivate) {
        return `import Component from './private';

/**
 * @sw-package ${packageName}
 */

Shopware.Component.${shape.kind}(Component);
`;
    }

    return `import Component, * as Module from './index';

/**
 * @sw-package ${packageName}
 */

Shopware.Component.${shape.kind}(Component);
Shopware._private.publish('components', '${name}', Module);
`;
}

/**
 * The global.d.ts of a component module, which types `Component.override()` on the component's name:
 * in `PublicComponents` for a public module, in `PrivateComponents` for a registered private one,
 * which the extension type surface leaves out. A nested component has no name to override, so it gets none.
 */
export function renderGlobalTypes({ name, packageName, shape }: Target): string | null {
    if (shape.kind === 'nested') {
        return null;
    }

    return `import type Component from './${shape.isPrivate ? 'private' : 'index'}';

/**
 * @sw-package ${packageName}
 */

declare global {
    interface ${shape.isPrivate ? 'PrivateComponents' : 'PublicComponents'} {
        '${name}': typeof Component;
    }
}
`;
}

/**
 * The private.ts of a nested component module: the `definePrivateComponent()` declaration with what its parent uses.
 * Registered components get none, because other code reaches them by their name.
 *
 * @example
 * renderPrivate({ name: 'sw-card', packageName: 'framework', shape: { kind: 'nested', parent: 'src/module/sw-x' } }, api);
 * // → "… definePrivateComponent({ name: 'sw-card', load: () => import('./src/sw-card'), public: { props: [ … ] } })"
 */
export function renderPrivate({ name, packageName, shape }: Target, api: ComponentApi): string | null {
    if (!isPrivateShape(shape)) {
        return null;
    }

    const base = shape.kind === 'extend' ? `\n    extends: '${shape.base}',` : '';

    return `import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package ${packageName}
 *
${describePrivateLists(shape)}
 */
/** @private */
export default definePrivateComponent({
    name: '${name}',${base}
    load: () => import('./src/${name}'),
    public: {
${renderLists(shape.kind === 'nested' ? NESTED_LISTS : ALL_LISTS, api)}
    },
});
`;
}
