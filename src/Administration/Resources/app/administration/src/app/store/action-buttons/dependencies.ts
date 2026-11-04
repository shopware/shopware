/**
 * @sw-package framework
 *
 * Everything actionButtons store needs from outside its mini-module.
 */
/** @private */
export type { actionButtonAdd } from '@shopware-ag/meteor-admin-sdk/es/ui/action-button';

/** @private */
export { defineStoreOptions, type StoreOf } from 'src/app/store/define-store-options';

/** @private */
export const getStore = <Id extends keyof PiniaRootState>(id: Id) => Shopware.Store.get(id);
