/**
 * @sw-package checkout
 *
 * Everything sw-order-create-promotion-modal needs from outside its mini-module.
 */
import { cartStoreService } from 'src/app/service-names';
import { swOrderStoreId } from '../../store/order.store';

/** @private */
export { DiscountScopes, DiscountTypes } from 'src/module/sw-promotion-v2/helper/promotion.helper';

/** @private */
export const getCartStoreService = () => Shopware.Service(cartStoreService);

/** @private */
export const useOrderStore = () => Shopware.Store.get(swOrderStoreId);

/** @private */
export const currency = ((...args: unknown[]) =>
    (Shopware.Utils.format.currency as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.format.currency;
