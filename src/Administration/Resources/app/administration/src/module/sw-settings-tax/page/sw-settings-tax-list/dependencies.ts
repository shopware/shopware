/**
 * @sw-package checkout
 *
 * Everything sw-settings-tax-list needs from outside its mini-module.
 */
import { cacheService } from 'src/app/service-names';
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
    repositoryFactory as repositoryFactoryService,
    systemConfigApiService,
} from 'src/app/service-names';

/** @private */
export const getCacheService = () => Shopware.Service(cacheService);

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;
