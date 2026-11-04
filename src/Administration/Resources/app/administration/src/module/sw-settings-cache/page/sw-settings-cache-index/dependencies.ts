/**
 * @sw-package framework
 *
 * Everything sw-settings-cache-index needs from outside its mini-module.
 */
import { notificationStoreId } from 'src/app/store/notification';

/** @private */
export { POLL_BACKGROUND_INTERVAL, POLL_FOREGROUND_INTERVAL } from 'src/core/worker/worker-notification-listener';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { cacheApiService, feature as featureService } from 'src/app/service-names';

/** @private */
export const useNotificationStore = () => Shopware.Store.get(notificationStoreId);
