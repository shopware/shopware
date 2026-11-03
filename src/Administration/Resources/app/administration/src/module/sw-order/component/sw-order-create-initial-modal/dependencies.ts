/**
 * @sw-package checkout
 *
 * Everything sw-order-create-initial-modal needs from outside its mini-module.
 */
import { cartStoreService } from 'src/app/service-names';
import { swOrderStoreId } from '../../store/order.store';

/** @private */
export type { TabItem } from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { feature as featureService } from 'src/app/service-names';
/** @private */
export { default as cartNotificationMixin } from '../../mixin/cart-notification.mixin';
/** @private */
export { LineItemType } from '../../order.types';
/** @private */
export type { Cart, CartDelivery, ContextSwitchParameters, LineItem, SalesChannelContext } from '../../order.types';

/** @private */
export const getCartStoreService = () => Shopware.Service(cartStoreService);

/** @private */
export const useOrderStore = () => Shopware.Store.get(swOrderStoreId);
