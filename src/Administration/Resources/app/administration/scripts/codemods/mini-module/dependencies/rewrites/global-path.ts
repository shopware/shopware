/**
 * @sw-package framework
 * @private
 */

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
 * `Shopware.Data.Criteria` and the other classes and objects of the global become imports of the module that
 * holds the same value, as deep along the path as an export reaches.
 * A function becomes a function in dependencies.ts that calls it through the global, so a spy or a replacement
 * on the global still applies, and the call keeps the object it was called on.
 *
 * @example
 * // const { Criteria } = Shopware.Data; … new Criteria(1, 25)
 * // → new Criteria(1, 25)
 * // dependencies.ts: export { default as Criteria } from 'src/core/data/criteria.data';
 * // Shopware.Utils.format.localeName(locale)
 * // → localeName(locale)
 * // dependencies.ts: export const localeName = (...args: Parameters<typeof Shopware.Utils.format.localeName>) =>
 * //     Shopware.Utils.format.localeName(...args);
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

    if (source.kind === 'function') {
        const global = `Shopware.${use.path.slice(0, source.consumed).join('.')}`;
        return replaceNode(node, context, source.name, [
            {
                kind: 'accessor',
                name: source.name,
                order: 5,
                body: `(...args: Parameters<typeof ${global}>) => ${global}(...args)`,
                imports: [],
            },
        ]);
    }

    return replaceNode(node, context, source.name, [
        { kind: 'reexport', module: source.module, imported: source.imported, exported: source.name },
    ]);
};
