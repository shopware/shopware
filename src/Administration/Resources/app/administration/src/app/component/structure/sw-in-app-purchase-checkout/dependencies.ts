/**
 * @sw-package checkout
 *
 * Everything sw-in-app-purchase-checkout needs from outside its mini-module.
 */
import { inAppPurchaseCheckoutStoreId } from 'src/app/store/in-app-purchase-checkout.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export type { InAppPurchaseRequest } from '../../../store/in-app-purchase-checkout.store';

/** @private */
export const useInAppPurchaseCheckoutStore = () => Shopware.Store.get(inAppPurchaseCheckoutStoreId);
