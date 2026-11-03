/**
 * @sw-package framework
 * @private
 */

import { isWritten } from '../global-uses';
import type { UseRewrite } from '../model';
import { upperFirst } from '../model';
import { prefixNode, replaceNode } from './helpers';

/**
 * `Shopware.Context.api` and `Shopware.Context.app` become functions in dependencies.ts, because the context
 * changes while the Administration runs, for example the language.
 *
 * @example
 * // repository.search(criteria, Shopware.Context.api)
 * // → repository.search(criteria, getApiContext())
 * // dependencies.ts: export const getApiContext = () => Shopware.Context.api;
 */
export const rewriteContext: UseRewrite = (use, context) => {
    const [root, part] = use.path;
    const node = prefixNode(use, 2);
    if (root !== 'Context' || (part !== 'api' && part !== 'app') || !node || isWritten(node)) {
        return null;
    }

    const name = `get${upperFirst(part)}Context`;
    if (!context.isFree(name)) {
        return null;
    }

    return replaceNode(node, context, `${name}()`, [
        { kind: 'accessor', name, order: 3, body: `() => Shopware.Context.${part}`, imports: [] },
    ]);
};
