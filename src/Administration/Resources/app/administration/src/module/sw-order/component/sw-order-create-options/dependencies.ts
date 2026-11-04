/**
 * @sw-package checkout
 *
 * Everything sw-order-create-options needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context';
import { swOrderStoreId } from '../../store/order.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export type { default as CriteriaType } from 'src/core/data/criteria.data';
/** @private */
export type { Cart, CartDelivery, ContextSwitchParameters } from '../../order.types';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const useOrderStore = () => Shopware.Store.get(swOrderStoreId);
