/**
 * @sw-package checkout
 *
 * Everything sw-order-address-selection needs from outside its mini-module.
 */
import { errorStoreId } from 'src/app/store/error';
import { swOrderDetailStoreId } from '../../store/order-detail.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as ShopwareError } from 'src/core/data/ShopwareError';
/** @private */
export { required } from 'src/core/service/validation.service';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { customSnippetApiService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { default as EntityValidationService } from 'src/app/service/entity-validation.service';

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);

/** @private */
export const useOrderDetailStore = () => Shopware.Store.get(swOrderDetailStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const cloneDeep = ((...args: unknown[]) =>
    (Shopware.Utils.object.cloneDeep as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.cloneDeep;
