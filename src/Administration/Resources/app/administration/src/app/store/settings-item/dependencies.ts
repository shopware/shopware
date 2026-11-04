/**
 * @sw-package framework
 *
 * Everything settingsItems store needs from outside its mini-module.
 */
/** @private */
export const hasOwnProperty = ((...args: unknown[]) =>
    (Shopware.Utils.object.hasOwnProperty as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.object.hasOwnProperty;

/** @private */
export { defineStoreOptions, type StoreOf } from 'src/app/store/define-store-options';

/** @private */
export const getStore = <Id extends keyof PiniaRootState>(id: Id) => Shopware.Store.get(id);
