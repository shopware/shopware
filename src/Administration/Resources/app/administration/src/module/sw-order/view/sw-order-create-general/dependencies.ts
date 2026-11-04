/**
 * @sw-package checkout
 *
 * Everything sw-order-create-general needs from outside its mini-module.
 */
import { currencyFilterName } from 'src/app/filter/currency.filter';
import { swOrderStoreId } from '../../store/order.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { default as cartNotificationMixin } from '../../mixin/cart-notification.mixin';
/** @private */
export type { CalculatedTax, Cart, CartDelivery, LineItem, PromotionCodeTag, SalesChannelContext } from '../../order.types';

/** @private */
export const useOrderStore = () => Shopware.Store.get(swOrderStoreId);

/** @private */
export const getCurrencyFilter = () => Shopware.Filter.getByName(currencyFilterName);

/** @private */
export const currency = ((...args: unknown[]) =>
    (Shopware.Utils.format.currency as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.format.currency;

/** @private */
export const get = ((...args: unknown[]) =>
    (Shopware.Utils.get as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.get;

/** @private */
export const slice = ((...args: unknown[]) =>
    (Shopware.Utils.array.slice as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.array.slice;
