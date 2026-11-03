/**
 * @sw-package checkout
 *
 * Everything sw-order-create-address-modal needs from outside its mini-module.
 */
import { repositoryFactory } from 'src/app/service-names';
import { errorStoreId } from 'src/app/store/error.store';
import { swOrderStoreId } from '../../store/order.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as ShopwareError } from 'src/core/data/ShopwareError';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';

/** @private */
export const getRepositoryFactory = () => Shopware.Service(repositoryFactory);

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);

/** @private */
export const useOrderStore = () => Shopware.Store.get(swOrderStoreId);
