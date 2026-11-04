/**
 * @sw-package checkout
 *
 * Everything sw-order-create-details needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context';
import { swOrderStoreId } from '../../store/order.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { cartStoreService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export type { default as CriteriaType } from '../../../../core/data/criteria.data';
/** @private */
export type { default as Repository } from '../../../../core/data/repository.data';
/** @private */
export { get } from '../../../../core/service/utils/object.utils';
/** @private */
export { default as cartNotificationMixin } from '../../mixin/cart-notification.mixin';
/** @private */
export { LineItemType } from '../../order.types';
/** @private */
export type {
    Cart,
    CartDelivery,
    ContextSwitchParameters,
    LineItem,
    PromotionCodeTag,
    SalesChannelContext,
} from '../../order.types';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const useOrderStore = () => Shopware.Store.get(swOrderStoreId);
