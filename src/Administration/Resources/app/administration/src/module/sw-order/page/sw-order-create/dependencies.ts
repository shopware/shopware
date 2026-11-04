/**
 * @sw-package checkout
 *
 * Everything sw-order-create needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context';
import { swOrderStoreId } from '../../store/order.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { feature as featureService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const useOrderStore = () => Shopware.Store.get(swOrderStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;
