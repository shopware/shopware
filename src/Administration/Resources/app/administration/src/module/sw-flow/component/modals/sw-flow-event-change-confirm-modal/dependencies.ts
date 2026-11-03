/**
 * @sw-package after-sales
 *
 * Everything sw-flow-event-change-confirm-modal needs from outside its mini-module.
 */
import { swFlowStoreId } from '../../../store/flow.store';

/** @private */
export { mapState } from 'pinia';
/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';

/** @private */
export const useFlowStore = () => Shopware.Store.get(swFlowStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;
