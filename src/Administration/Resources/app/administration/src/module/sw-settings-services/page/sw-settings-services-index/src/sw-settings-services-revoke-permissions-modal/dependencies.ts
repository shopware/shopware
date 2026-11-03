/**
 * @sw-package framework
 *
 * Everything sw-settings-services-revoke-permissions-modal needs from outside its mini-module.
 */
import { notificationStoreId } from 'src/app/store/notification.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as extractErrorMessage } from '../../../../composables/extract-error';
/** @private */
export { revokePermissions } from '../../../../composables/permissions';

/** @private */
export const useNotificationStore = () => Shopware.Store.get(notificationStoreId);
