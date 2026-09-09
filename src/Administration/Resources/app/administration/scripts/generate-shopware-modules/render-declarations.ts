/**
 * @sw-package framework
 *
 * Renders the ambient declarations for every `shopware:*` specifier.
 *
 * Each module is declared with `export =`, which is what lets one specifier serve both an import of the
 * whole thing and an import of its members:
 *
 *     import debug, { warn } from 'shopware:utils/debug';
 *
 * The types come from the objects the global already holds and from the `MixinContainer` and
 * `PiniaRootState` interfaces, so a declaration cannot describe a shape the runtime does not have.
 */

import type { ModuleRegistry } from './extract-modules';

const UTILS_MODULE = 'src/core/service/util.service';
const DATA_MODULE = 'src/core/data/index';

/**
 * The command that rewrites this file, named in the file itself.
 *
 * A stale declaration is a lie about what can be imported, and the drift test points here.
 */
export const REGENERATE_COMMAND = 'composer admin:generate-shopware-modules';

function block(specifier: string, body: string[]): string {
    return [
        `declare module '${specifier}' {`,
        // A blank line keeps its emptiness: an indented one would fail the formatting check.
        ...body.map((line) => (line === '' ? '' : `    ${line}`)),
        '}',
        '',
    ].join('\n');
}

/** A subpath of a branch that is itself an object, e.g. `shopware:utils/debug`. */
function branchSubpath(specifier: string, branchModule: string, key: string): string {
    return block(specifier, [
        `import type branch from '${branchModule}';`,
        '',
        `const member: (typeof branch)['${key}'];`,
        '',
        'export = member;',
    ]);
}

function barrel(specifier: string, branchModule: string): string {
    return block(specifier, [
        `import type branch from '${branchModule}';`,
        '',
        'const members: typeof branch;',
        '',
        'export = members;',
    ]);
}

/**
 * The whole declaration file.
 *
 * Specifiers are emitted in registry order so a regenerated file only differs where the registry did.
 */
export function renderDeclarations(registry: ModuleRegistry): string {
    const blocks: string[] = [];

    blocks.push(barrel('shopware:utils', UTILS_MODULE));
    Object.keys(registry['shopware:utils'].subpaths).forEach((key) =>
        blocks.push(branchSubpath(`shopware:utils/${key}`, UTILS_MODULE, key)),
    );

    blocks.push(barrel('shopware:data', DATA_MODULE));
    Object.keys(registry['shopware:data'].subpaths).forEach((key) =>
        blocks.push(branchSubpath(`shopware:data/${key}`, DATA_MODULE, key)),
    );

    Object.keys(registry['shopware:mixins'].subpaths).forEach((key) =>
        blocks.push(
            block(`shopware:mixins/${key}`, [
                `const mixin: MixinContainer['${key}'];`,
                '',
                'export = mixin;',
            ]),
        ),
    );

    Object.keys(registry['shopware:stores'].subpaths).forEach((key) =>
        blocks.push(
            block(`shopware:stores/${key}`, [
                `const useStore: () => PiniaRootState['${key}'];`,
                '',
                'export = useStore;',
            ]),
        ),
    );

    return [
        '/**',
        ' * @sw-package framework',
        ' *',
        ' * Types for the `shopware:*` modules, which expose the global `Shopware` object as ordinary',
        ' * imports. `build/vite-plugins/virtual-shopware-modules` generates their runtime counterpart from',
        ' * the same `shopware-modules.json`.',
        ' *',
        ` * Generated. Run \`${REGENERATE_COMMAND}\` after adding a utility, DAL class, mixin, or store.`,
        ' */',
        '',
        '/* eslint-disable @typescript-eslint/consistent-type-imports */',
        '',
        ...blocks,
    ].join('\n');
}
