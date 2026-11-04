/**
 * @sw-package framework
 *
 * Everything sw-modals-renderer needs from outside its mini-module.
 */
import { modalsStoreId } from 'src/app/store/modals';

/** @private */
export type { buttonProps } from '@shopware-ag/meteor-admin-sdk/es/ui/modal';
/** @private */
export { default as DOMPurify } from 'dompurify';
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export type { ModalItemEntry } from 'src/app/store/modals';

/** @private */
export const useModalsStore = () => Shopware.Store.get(modalsStoreId);
