/**
 * @sw-package fundamentals@after-sales
 *
 * Everything sw-settings-rule-list needs from outside its mini-module.
 */
import { dateFilterName } from 'src/app/filter/date.filter';
import { contextStoreId } from 'src/app/store/context';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as listingMixin } from 'src/app/mixin/listing.mixin';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export {
    acl as aclService,
    filterFactory as filterFactoryService,
    filterService,
    repositoryFactory as repositoryFactoryService,
    ruleConditionDataProviderService,
} from 'src/app/service-names';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;
