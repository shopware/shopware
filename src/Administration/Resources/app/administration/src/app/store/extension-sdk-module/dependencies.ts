/**
 * @sw-package framework
 *
 * Everything extensionSdkModules store needs from outside its mini-module.
 */
/** @private */
export type { smartBarButtonAdd } from '@shopware-ag/meteor-admin-sdk/es/ui/main-module/';

/** @private */
export const md5 = ((...args: unknown[]) =>
    (Shopware.Utils.format.md5 as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.format.md5;

/** @private */
export { defineStoreOptions, type StoreOf } from 'src/app/store/define-store-options';

/** @private */
export const getStore = <Id extends keyof PiniaRootState>(id: Id) => Shopware.Store.get(id);
