/**
 * @sw-package framework
 *
 * Everything the adminHelpCenter store needs from outside its mini-module.
 */

/** @private */
export { defineStoreOptions, type StoreOf } from 'src/app/store/define-store-options';

/** @private */
export const getStore = <Id extends keyof PiniaRootState>(id: Id) => Shopware.Store.get(id);
