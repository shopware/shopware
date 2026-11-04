/**
 * @sw-package checkout
 *
 * Everything sw-order-new-customer-modal needs from outside its mini-module.
 */
import { errorStoreId } from 'src/app/store/error';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as ShopwareError } from 'src/core/data/ShopwareError';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export {
    customerValidationService,
    feature as featureService,
    numberRangeService,
    repositoryFactory as repositoryFactoryService,
    systemConfigApiService,
} from 'src/app/service-names';
/** @private */
export { default as EntityValidationService } from 'src/app/service/entity-validation.service';
/** @private */
export { mapPageErrors } from 'src/app/service/map-errors.service';
/** @private */
export { default as CUSTOMER } from 'src/module/sw-customer/constant/sw-customer.constant';

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;
