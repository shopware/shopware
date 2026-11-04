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

/** @private */
export { useExtensionsStore } from 'src/app/store/extensions';

/** @private */
export { useExtensionSdkModulesStore } from 'src/app/store/extension-sdk-module';
