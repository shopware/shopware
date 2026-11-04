/**
 * @sw-package framework
 *
 * Everything sw-notifications needs from outside its mini-module.
 */
import { notificationStoreId } from 'src/app/store/notification';

/** @private */
export { default as notificationTranslationMixin } from 'src/app/mixin/notification-translation.mixin';
/** @private */
export { feature as featureService } from 'src/app/service-names';

/** @private */
export const useNotificationStore = () => Shopware.Store.get(notificationStoreId);

/** @private */
export const isUrl = ((...args: unknown[]) =>
    (Shopware.Utils.string.isUrl as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.string.isUrl;
