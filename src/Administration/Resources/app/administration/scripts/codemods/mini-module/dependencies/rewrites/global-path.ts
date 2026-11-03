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
 * `Shopware.Data.Criteria`, `Shopware.Utils.format.fileSize` and the like become imports of the module that holds
 * the same value, as deep along the path as an export reaches. A call keeps the object it was called on,
 * unless the function never reads `this`.
 *
 * @example
 * // const { Criteria } = Shopware.Data; … new Criteria(1, 25)
 * // → new Criteria(1, 25)
 * // dependencies.ts: export { default as Criteria } from 'src/core/data/criteria.data';
 */
export const rewriteGlobalPath: UseRewrite = (use, context) => {
    if (!STATIC_ROOTS.has(use.path[0])) {
        return null;
    }

    const isCallee = !!use.call;
    const source = context.index.resolveGlobal(use.path, isCallee);
    const node = source && prefixNode(use, source.consumed);
    if (!source || !node || (source.consumed === use.path.length && isWritten(use.node)) || !context.isFree(source.name)) {
        return null;
    }

    return replaceNode(node, context, source.name, [
        { kind: 'reexport', module: source.module, imported: source.imported, exported: source.name },
    ]);
};
