/**
 * @sw-package after-sales
 *
 * Everything sw-flow-sequence-action-error needs from outside its mini-module.
 */
import { swFlowStoreId } from '../../store/flow.store';

/** @private */
export { mapState } from 'pinia';

/** @private */
export const useFlowStore = () => Shopware.Store.get(swFlowStoreId);
