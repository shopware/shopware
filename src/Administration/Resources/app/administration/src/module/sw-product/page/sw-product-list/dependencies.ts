/**
 * @sw-package inventory
 *
 * Everything sw-product-list needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { currencyFilterName } from 'src/app/filter/currency.filter';
import { dateFilterName } from 'src/app/filter/date.filter';
import { stockColorVariantFilterName } from 'src/app/filter/stock-color-variant.filter';
import { filterService } from 'src/app/service-names';
import { contextStoreId } from 'src/app/store/context.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as listingMixin } from 'src/app/mixin/listing.mixin';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
/** @private */
export {
    acl as aclService,
    filterFactory as filterFactoryService,
    numberRangeService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';
/** @private */
export { searchRankingPoint } from 'src/app/service/search-ranking.service';

/** @private */
export const getFilterService = (): ServiceContainer['filterService'] => Shopware.Service(filterService);

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getCurrencyFilter = () => Shopware.Filter.getByName(currencyFilterName);

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);

/** @private */
export const getStockColorVariantFilter = () => Shopware.Filter.getByName(stockColorVariantFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const getAppContext = () => Shopware.Context.app;

/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);

/** @private */
export const cloneDeep = ((...args: unknown[]) =>
    (Shopware.Utils.object.cloneDeep as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.cloneDeep;
