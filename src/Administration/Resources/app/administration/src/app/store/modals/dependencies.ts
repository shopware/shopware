/**
 * @sw-package framework
 *
 * Everything modals store needs from outside its mini-module.
 */
/** @private */
export type { uiModalOpen } from '@shopware-ag/meteor-admin-sdk/es/ui/modal';

/** @private */
export { defineStoreOptions, type StoreOf } from 'src/app/store/define-store-options';

/** @private */
export const getStore = <Id extends keyof PiniaRootState>(id: Id) => Shopware.Store.get(id);

/** @private */
export { useExtensionsStore } from 'src/app/store/extensions';
