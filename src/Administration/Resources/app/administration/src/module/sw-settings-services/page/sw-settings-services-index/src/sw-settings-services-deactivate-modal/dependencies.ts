/**
 * @sw-package framework
 *
 * Everything sw-settings-services-deactivate-modal needs from outside its mini-module.
 */
import { notificationStoreId } from 'src/app/store/notification.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as extractError } from '../../../../composables/extract-error';

/** @private */
export const useNotificationStore = () => Shopware.Store.get(notificationStoreId);
