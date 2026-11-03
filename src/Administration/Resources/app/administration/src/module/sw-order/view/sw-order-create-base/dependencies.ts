/**
 * @sw-package checkout
 *
 * Everything sw-order-create-base needs from outside its mini-module.
 */
import { currencyFilterName } from 'src/app/filter/currency.filter';
import { cartStoreService, repositoryFactory } from 'src/app/service-names';
import { swOrderStoreId } from '../../store/order.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';

/** @private */
export const getCartStoreService = () => Shopware.Service(cartStoreService);

/** @private */
export const getRepositoryFactory = () => Shopware.Service(repositoryFactory);

/** @private */
export const useOrderStore = () => Shopware.Store.get(swOrderStoreId);

/** @private */
export const getCurrencyFilter = () => Shopware.Filter.getByName(currencyFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const date = ((...args: unknown[]) =>
    (Shopware.Utils.format.date as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.format.date;

/** @private */
export const get = ((...args: unknown[]) =>
    (Shopware.Utils.get as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.get;

/** @private */
export const slice = ((...args: unknown[]) =>
    (Shopware.Utils.array.slice as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.array.slice;
