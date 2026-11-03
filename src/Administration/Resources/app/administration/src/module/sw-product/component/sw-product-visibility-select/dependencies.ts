/**
 * @sw-package inventory
 *
 * Everything sw-product-visibility-select needs from outside its mini-module.
 */
import { swProductDetailStoreId } from '../../page/sw-product-detail/store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';

/** @private */
export const useProductDetailStore = () => Shopware.Store.get(swProductDetailStoreId);
