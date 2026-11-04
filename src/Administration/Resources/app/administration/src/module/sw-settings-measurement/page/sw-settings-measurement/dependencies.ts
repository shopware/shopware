/**
 * @sw-package inventory
 *
 * Everything sw-settings-measurement needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context';
import { errorStoreId } from 'src/app/store/error';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as ShopwareError } from 'src/core/data/ShopwareError';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export {
    acl as aclService,
    repositoryFactory as repositoryFactoryService,
    systemConfigApiService,
} from 'src/app/service-names';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);
