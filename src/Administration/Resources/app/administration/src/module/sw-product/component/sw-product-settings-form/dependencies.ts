/**
 * @sw-package inventory
 *
 * Everything sw-product-settings-form needs from outside its mini-module.
 */
import { swProductDetailStoreId } from '../../page/sw-product-detail/store';

/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export const useProductDetailStore = () => Shopware.Store.get(swProductDetailStoreId);
