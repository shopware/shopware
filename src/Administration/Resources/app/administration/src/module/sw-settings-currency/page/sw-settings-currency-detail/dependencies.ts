/**
 * @sw-package fundamentals@framework
 *
 * Everything sw-settings-currency-detail needs from outside its mini-module.
 */
import { cacheService } from 'src/app/service-names';
import { contextStoreId } from 'src/app/store/context';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { cloneDeep } from 'src/core/service/utils/object.utils';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder';
/** @private */
export {
    acl as aclService,
    customFieldDataProviderService,
    feature as featureService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export const getCacheService = () => Shopware.Service(cacheService);

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);
