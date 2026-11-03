/**
 * @sw-package framework
 *
 * Everything sw-notifications needs from outside its mini-module.
 */
import { notificationStoreId } from 'src/app/store/notification.store';

/** @private */
export { default as notificationTranslationMixin } from 'src/app/mixin/notification-translation.mixin';
/** @private */
export { feature as featureService } from 'src/app/service-names';

/** @private */
export const useNotificationStore = () => Shopware.Store.get(notificationStoreId);

/** @private */
export const isUrl = (...args: Parameters<typeof Shopware.Utils.string.isUrl>) => Shopware.Utils.string.isUrl(...args);
