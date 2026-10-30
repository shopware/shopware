/**
 * @sw-package checkout
 *
 * Everything the order list needs from outside its mini-module.
 */
import { currencyFilterName } from 'src/app/filter/currency.filter';
import { dateFilterName } from 'src/app/filter/date.filter';
import { assetFilterName } from 'src/app/filter/asset.filter';
import type CriteriaType from 'src/core/data/criteria.data';
import { filterService } from 'src/app/service-names';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as listingMixin } from 'src/app/mixin/listing.mixin';
/** @private */
export { getPrimaryDelivery } from 'src/core/order/primary-delivery';
/** @private */
export {
    acl as aclService,
    feature as featureService,
    filterFactory as filterFactoryService,
    repositoryFactory as repositoryFactoryService,
    stateStyleDataProviderService,
} from 'src/app/service-names';

/** @private */
export const getCurrencyFilter = () => Shopware.Filter.getByName(currencyFilterName);

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** The filters a user set in a listing, stored across visits. @private */
export const getFilterService = () =>
    Shopware.Service(filterService) as {
        mergeWithStoredFilters: (storeKey: string, criteria: CriteriaType) => Promise<CriteriaType>;
    };

/** @private */
export const getAppContext = () => Shopware.Context.app;

/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);
