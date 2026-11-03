/**
 * @sw-package checkout
 *
 * Everything sw-settings-tax-detail needs from outside its mini-module.
 */
import { cacheService } from 'src/app/service-names';
import { contextStoreId } from 'src/app/store/context.store';

/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export {
    acl as aclService,
    customFieldDataProviderService,
    repositoryFactory as repositoryFactoryService,
    systemConfigApiService,
} from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export const getCacheService = () => Shopware.Service(cacheService);

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);
