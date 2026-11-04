/**
 * @sw-package framework
 *
 * Everything extensionComponentSections store needs from outside its mini-module.
 */
/** @private */
export type { uiComponentSectionRenderer } from '@shopware-ag/meteor-admin-sdk/es/ui/component-section';
/** @private */
export { reactive } from 'vue';

/** @private */
export { defineStoreOptions, type StoreOf } from 'src/app/store/define-store-options';

/** @private */
export const getStore = <Id extends keyof PiniaRootState>(id: Id) => Shopware.Store.get(id);

/** @private */
export { useExtensionsStore } from 'src/app/store/extensions';
