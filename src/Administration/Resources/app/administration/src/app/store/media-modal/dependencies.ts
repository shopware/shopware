/**
 * @sw-package framework
 *
 * Everything mediaModal store needs from outside its mini-module.
 */
/** @private */
export type { uiMediaModalOpen, uiMediaModalOpenSaveMedia } from '@shopware-ag/meteor-admin-sdk/es/ui/media-modal';

/** @private */
export { defineStoreOptions, type StoreOf } from 'src/app/store/define-store-options';

/** @private */
export const getStore = <Id extends keyof PiniaRootState>(id: Id) => Shopware.Store.get(id);
