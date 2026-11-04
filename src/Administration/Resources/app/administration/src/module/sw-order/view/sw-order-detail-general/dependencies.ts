/**
 * @sw-package checkout
 *
 * Everything sw-order-detail-general needs from outside its mini-module.
 */
import { currencyFilterName } from 'src/app/filter/currency.filter';
import { swOrderDetailStoreId } from '../../store/order-detail.store';

/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';

/** @private */
export const useOrderDetailStore = () => Shopware.Store.get(swOrderDetailStoreId);

/** @private */
export const getCurrencyFilter = () => Shopware.Filter.getByName(currencyFilterName);

/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);

/** @private */
export const cloneDeep = ((...args: unknown[]) =>
    (Shopware.Utils.object.cloneDeep as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.cloneDeep;

/** @private */
export const currency = ((...args: unknown[]) =>
    (Shopware.Utils.format.currency as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.format.currency;

/** @private */
export const slice = ((...args: unknown[]) =>
    (Shopware.Utils.array.slice as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.array.slice;
