/**
 * @sw-package after-sales
 *
 * Everything sw-flow-affiliate-and-campaign-code-modal needs from outside its mini-module.
 */
import { swFlowStoreId } from '../../../store/flow.store';

/** @private */
export { mapState } from 'pinia';
/** @private */
export { default as ShopwareError } from 'src/core/data/ShopwareError';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder';
/** @private */
export { flowBuilderService } from 'src/app/service-names';

/** @private */
export const useFlowStore = () => Shopware.Store.get(swFlowStoreId);
