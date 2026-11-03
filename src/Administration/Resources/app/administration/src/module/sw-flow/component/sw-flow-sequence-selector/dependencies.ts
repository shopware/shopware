/**
 * @sw-package after-sales
 *
 * Everything sw-flow-sequence-selector needs from outside its mini-module.
 */
import { swFlowStoreId } from '../../store/flow.store';

/** @private */
export const useFlowStore = () => Shopware.Store.get(swFlowStoreId);
