/**
 * @sw-package checkout
 *
 * Everything sw-order-line-items-grid-sales-channel needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { currencyFilterName } from 'src/app/filter/currency.filter';
import { repositoryFactory } from 'src/app/service-names';
import { swOrderStoreId } from '../../store/order.store';

/** @private */
export { LineItemType } from '../../order.types';

/** @private */
export const getRepositoryFactory = () => Shopware.Service(repositoryFactory);

/** @private */
export const useOrderStore = () => Shopware.Store.get(swOrderStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getCurrencyFilter = () => Shopware.Filter.getByName(currencyFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const currency = ((...args: unknown[]) =>
    (Shopware.Utils.format.currency as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.format.currency;

/** @private */
export const get = ((...args: unknown[]) =>
    (Shopware.Utils.get as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.get;
