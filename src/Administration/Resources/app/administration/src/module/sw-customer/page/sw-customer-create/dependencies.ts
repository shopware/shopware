/**
 * @sw-package checkout
 *
 * Everything sw-customer-create needs from outside its mini-module.
 */
import { salutationFilterName } from 'src/app/filter/salutation.filter';
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
    customerValidationService,
    numberRangeService,
    repositoryFactory as repositoryFactoryService,
    systemConfigApiService,
} from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';
/** @private */
export { default as CUSTOMER } from '../../constant/sw-customer.constant';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);

/** @private */
export const getSalutationFilter = () => Shopware.Filter.getByName(salutationFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;
