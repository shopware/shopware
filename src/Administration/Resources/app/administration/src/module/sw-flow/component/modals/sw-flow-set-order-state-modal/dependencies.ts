/**
 * @sw-package after-sales
 *
 * Everything sw-flow-set-order-state-modal needs from outside its mini-module.
 */
import { swFlowStoreId } from '../../../store/flow.store';

/** @private */
export { mapState } from 'pinia';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const useFlowStore = () => Shopware.Store.get(swFlowStoreId);
