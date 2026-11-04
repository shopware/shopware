/**
 * @sw-package framework
 *
 * Everything menuItem store needs from outside its mini-module.
 */
/** @private */
export type { menuItemAdd } from '@shopware-ag/meteor-admin-sdk/es/ui/menu';

/** @private */
export { defineStoreOptions, type StoreOf } from 'src/app/store/define-store-options';

/** @private */
export const getStore = <Id extends keyof PiniaRootState>(id: Id) => Shopware.Store.get(id);

/** @private */
export { useExtensionsStore } from 'src/app/store/extensions';

/** @private */
export { useExtensionSdkModulesStore } from 'src/app/store/extension-sdk-module';

/** @private */
export { useAdminMenuStore } from 'src/app/store/admin-menu';
