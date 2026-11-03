/**
 * @sw-package inventory
 *
 * Everything sw-product-feature-set-form needs from outside its mini-module.
 */
import { swProductDetailStoreId } from '../../page/sw-product-detail/store';

/** @private */
export const useProductDetailStore = () => Shopware.Store.get(swProductDetailStoreId);
