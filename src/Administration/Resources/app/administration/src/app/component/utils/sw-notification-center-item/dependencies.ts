/**
 * @sw-package framework
 *
 * Everything sw-notification-center-item needs from outside its mini-module.
 */
import { notificationStoreId } from 'src/app/store/notification.store';

/** @private */
export { default as notificationTranslationMixin } from 'src/app/mixin/notification-translation.mixin';

/** @private */
export const useNotificationStore = () => Shopware.Store.get(notificationStoreId);

/** @private */
export const isUrl = (...args: Parameters<typeof Shopware.Utils.string.isUrl>) => Shopware.Utils.string.isUrl(...args);
