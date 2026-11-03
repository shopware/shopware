/**
 * @sw-package after-sales
 *
 * Everything sw-order-detail-documents needs from outside its mini-module.
 */
import { swOrderDetailStoreId } from '../../store/order-detail.store';

/** @private */
export const useOrderDetailStore = () => Shopware.Store.get(swOrderDetailStoreId);
