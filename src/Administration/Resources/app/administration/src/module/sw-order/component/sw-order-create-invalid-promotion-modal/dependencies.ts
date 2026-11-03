/**
 * @sw-package checkout
 *
 * Everything sw-order-create-invalid-promotion-modal needs from outside its mini-module.
 */
import { swOrderStoreId } from '../../store/order.store';

/** @private */
export const useOrderStore = () => Shopware.Store.get(swOrderStoreId);
