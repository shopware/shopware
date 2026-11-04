/**
 * @sw-package discovery
 *
 * Everything sw-cms-list needs from outside its mini-module.
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
export { default as userSettingsMixin } from 'src/app/mixin/user-settings.mixin';
/** @private */
export {
    acl as aclService,
    cmsPageTypeService,
    feature as featureService,
    repositoryFactory as repositoryFactoryService,
    systemConfigApiService,
} from 'src/app/service-names';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const getAppContext = () => Shopware.Context.app;

/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);
