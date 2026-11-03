/**
 * @sw-package after-sales
 *
 * Everything sw-review-list needs from outside its mini-module.
 */
import { dateFilterName } from 'src/app/filter/date.filter';
import { filterService } from 'src/app/service-names';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as listingMixin } from 'src/app/mixin/listing.mixin';
/** @private */
export {
    acl as aclService,
    filterFactory as filterFactoryService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const getFilterService = (): ServiceContainer['filterService'] => Shopware.Service(filterService);

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;
