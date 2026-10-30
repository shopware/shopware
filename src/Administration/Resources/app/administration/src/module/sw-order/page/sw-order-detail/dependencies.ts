/**
 * @sw-package checkout
 *
 * Everything the order detail page needs from outside its mini-module.
 */
import { swOrderDetailStoreId } from '../../store/order-detail.store';
import { shopwareAppsStoreId } from 'src/app/store/shopware-apps.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as ApiService } from 'src/core/service/api.service';
/** @private */
export { array } from 'src/core/service/util.service';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { getCartErrorMessage } from '../../cart-error.helper';
/** @private */
export {
    acl as aclService,
    feature as featureService,
    orderService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** The order being edited, shared with the views and cards of the detail page. @private */
export const useOrderDetailStore = () => Shopware.Store.get(swOrderDetailStoreId);

/** The orders that app action buttons act on. @private */
export const useShopwareAppsStore = () => Shopware.Store.get(shopwareAppsStoreId);

/** @private */
export const publishData: typeof Shopware.ExtensionAPI.publishData = (...args) => Shopware.ExtensionAPI.publishData(...args);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);
