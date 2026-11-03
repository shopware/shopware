/**
 * @sw-package framework
 * @private
 */

import ts from 'typescript';
import { isWritten } from '../global-uses';
import type { UseRewrite } from '../model';
import { prefixNode, replaceNode } from './helpers';

/**
 * The parts of the `Shopware` global that are plain modules: the same values are imported from where they are defined.
 * Registries, getters and anything that changes at runtime are not among them.
 */
const STATIC_ROOTS = new Set([
    'Classes',
    'Constants',
    'Data',
    'ExtensionAPI',
    'Helper',
    'Utils',
]);

/**
 * A function that calls the function at a path of the global when it runs, typed as that function.
 * The parentheses around the cast keep the call on its object, as `Shopware.Utils.format.fileSize(...args)` does.
 */
const lateBound = (global: string) =>
    `((...args: unknown[]) => (${global} as (...args: unknown[]) => unknown)(...args)) as typeof ${global}`;

/**
 * `Shopware.Data.Criteria` and the other classes and objects of the global become imports of the module that
 * holds the same value, as deep along the path as an export reaches.
 * A function becomes a function in dependencies.ts that calls it through the global, so a spy or a replacement
 * on the global still applies, and the call keeps the object it was called on.
 * The function has the type of the original, also when that is generic, overloaded or returns `any`:
 * the wrapper itself passes `unknown` only, which TypeScript and typescript-eslint accept for every function.
 *
 * @example
 * // const { Criteria } = Shopware.Data; … new Criteria(1, 25)
 * // → new Criteria(1, 25)
 * // dependencies.ts: export { default as Criteria } from 'src/core/data/criteria.data';
 * // Shopware.Utils.format.localeName(locale)
 * // → localeName(locale)
 * // dependencies.ts: export const localeName = ((...args: unknown[]) =>
 * //     (Shopware.Utils.format.localeName as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.format.localeName;
 */
export const rewriteGlobalPath: UseRewrite = (use, context) => {
    if (!STATIC_ROOTS.has(use.path[0])) {
        return null;
    }

    const source = context.index.resolveGlobal(use.path);
    const node = source && prefixNode(use, source.consumed);
    if (!source || !node || (source.consumed === use.path.length && isWritten(use.node)) || !context.isFree(source.name)) {
        return null;
    }

    // A value from a package may be a function or a class: a call is reached through the global, `new` imports
    // it, and anything else needs judgement.
    const isNew = ts.isNewExpression(node.parent) && node.parent.expression === node;
    const asFunction =
        source.kind === 'function' || (source.fromPackage && !!use.call && source.consumed === use.path.length);
    if (source.kind === 'import' && source.fromPackage && source.consumed === use.path.length && !use.call && !isNew) {
        return null;
    }

    if (asFunction) {
        const global = `Shopware.${use.path.slice(0, source.consumed).join('.')}`;
        return replaceNode(node, context, source.name, [
            {
                kind: 'accessor',
                name: source.name,
                order: 5,
                body: lateBound(global),
                imports: [],
            },
        ]);
    }

    return replaceNode(node, context, source.name, [
        { kind: 'reexport', module: source.module, imported: source.imported, exported: source.name },
    ]);
};
