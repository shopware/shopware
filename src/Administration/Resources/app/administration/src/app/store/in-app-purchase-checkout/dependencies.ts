/**
 * @sw-package checkout
 *
 * Everything inAppPurchaseCheckout store needs from outside its mini-module.
 */
/** @private */
export type { iapCheckout } from '@shopware-ag/meteor-admin-sdk/es/iap';

/** @private */
export const getAppContext = () => Shopware.Context.app;

/** @private */
export { defineStoreOptions, type StoreOf } from 'src/app/store/define-store-options';

/** @private */
export const getStore = <Id extends keyof PiniaRootState>(id: Id) => Shopware.Store.get(id);
