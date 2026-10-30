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

export { default as Criteria } from 'src/core/data/criteria.data';
export { default as listingMixin } from 'src/app/mixin/listing.mixin';
export { getPrimaryDelivery } from 'src/core/order/primary-delivery';
export {
    acl as aclService,
    feature as featureService,
    filterFactory as filterFactoryService,
    repositoryFactory as repositoryFactoryService,
    stateStyleDataProviderService,
} from 'src/app/service-names';

export const getCurrencyFilter = () => Shopware.Filter.getByName(currencyFilterName);

export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);

export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** The filters a user set in a listing, stored across visits. */
export const getFilterService = () =>
    Shopware.Service(filterService) as {
        mergeWithStoredFilters: (storeKey: string, criteria: CriteriaType) => Promise<CriteriaType>;
    };

export const getAppContext = () => Shopware.Context.app;

export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);
