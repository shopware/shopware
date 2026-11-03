/**
 * @sw-package checkout
 *
 * Everything sw-order-state-history-modal needs from outside its mini-module.
 */
import { swOrderDetailStoreId } from '../../store/order-detail.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export type { default as CriteriaType } from 'src/core/data/criteria.data';
/** @private */
export type { default as RepositoryType } from 'src/core/data/repository.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { repositoryFactory as repositoryFactoryService, stateStyleDataProviderService } from 'src/app/service-names';

/** @private */
export const useOrderDetailStore = () => Shopware.Store.get(swOrderDetailStoreId);
