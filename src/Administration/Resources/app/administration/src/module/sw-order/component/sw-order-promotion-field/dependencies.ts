/**
 * @sw-package checkout
 *
 * Everything sw-order-promotion-field needs from outside its mini-module.
 */
import { swOrderDetailStoreId } from '../../store/order-detail.store';

/** @private */
export { default as ChangesetGenerator } from 'src/core/data/changeset-generator.data';
/** @private */
export { getCartErrorMessage } from '../../cart-error.helper';

/** @private */
export const useOrderDetailStore = () => Shopware.Store.get(swOrderDetailStoreId);
