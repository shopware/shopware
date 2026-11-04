/**
 * @sw-package checkout
 *
 * Everything sw-customer-detail needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context';
import { errorStoreId } from 'src/app/store/error';
import { shopwareAppsStoreId } from 'src/app/store/shopware-apps';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as ShopwareError } from 'src/core/data/ShopwareError';
/** @private */
export { default as discardDetailPageChangesMixin } from 'src/app/mixin/discard-detail-page-changes';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { default as salutationMixin } from 'src/app/mixin/salutation';
/** @private */
export {
    acl as aclService,
    customerGroupRegistrationService,
    customerValidationService,
    feature as featureService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';
/** @private */
export { mapPageErrors } from 'src/app/service/map-errors.service';
/** @private */
export { default as CUSTOMER } from '../../constant/sw-customer.constant';
/** @private */
export { default as errorConfig } from '../../error-config.json';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);

/** @private */
export const useShopwareAppsStore = () => Shopware.Store.get(shopwareAppsStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const publishData = ((...args: unknown[]) =>
    (Shopware.ExtensionAPI.publishData as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.ExtensionAPI.publishData;
