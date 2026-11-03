/**
 * @sw-package checkout
 *
 * Everything sw-promotion-v2-list needs from outside its mini-module.
 */
import { dateFilterName } from 'src/app/filter/date.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as listingMixin } from 'src/app/mixin/listing.mixin';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const getAppContext = () => Shopware.Context.app;

/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);
