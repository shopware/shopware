/**
 * @sw-package framework
 * @private
 */

import type { OutsideEdit, UseRewrite } from '../model';
import { camelCase, upperFirst } from '../model';
import { isPath, literalArgument, replaceNode } from './helpers';

/**
 * `Shopware.Store.get('x')` becomes a function in dependencies.ts that looks the store up by the id its file
 * exports, because a store is only there once it is registered. A store file without an exported id gets one.
 *
 * @example
 * // Shopware.Store.get('swProductDetail').product
 * // → useProductDetailStore().product
 * // dependencies.ts: export const useProductDetailStore = () => Shopware.Store.get(swProductDetailStoreId);
 */
export const rewriteStore: UseRewrite = (use, context) => {
    const id = literalArgument(use.call);
    const store = id === null || !isPath(use, 'Store', 'get') ? null : context.index.findStore(id);
    if (!use.call || !id || !store) {
        return null;
    }

    const exportName = store.exportName ?? `${camelCase(id)}StoreId`;
    const name = `use${upperFirst(camelCase(id).replace(/^sw(?=[A-Z])/, ''))}Store`;
    if (!context.isFree(name)) {
        return null;
    }

    const outsideEdits: OutsideEdit[] = store.exportName ? [] : [{ kind: 'store-id', file: store.file, id, exportName }];

    return replaceNode(
        use.call,
        context,
        `${name}()`,
        [
            {
                kind: 'accessor',
                name,
                order: 1,
                body: `() => Shopware.Store.get(${exportName})`,
                imports: [{ module: store.module, imported: exportName }],
            },
        ],
        outsideEdits,
    );
};
