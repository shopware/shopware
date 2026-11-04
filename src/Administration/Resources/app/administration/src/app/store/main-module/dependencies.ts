/**
 * @sw-package framework
 *
 * Everything the extensionMainModules store needs from outside its mini-module.
 */

/** @private */
export { defineStoreOptions, type StoreOf } from 'src/app/store/define-store-options';

/** @private */
export const getStore = <Id extends keyof PiniaRootState>(id: Id) => Shopware.Store.get(id);

/** @private */
export { useExtensionsStore } from 'src/app/store/extensions';

/** @private */
export { useExtensionSdkModulesStore } from 'src/app/store/extension-sdk-module';
