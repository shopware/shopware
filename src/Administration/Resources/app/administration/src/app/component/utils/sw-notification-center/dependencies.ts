/**
 * @sw-package framework
 *
 * Everything sw-notification-center needs from outside its mini-module.
 */
import { notificationStoreId } from 'src/app/store/notification.store';

/** @private */
export { default as EventBus } from 'src/core/service/utils/eventBus.utils';
/** @private */
export { POLL_BACKGROUND_INTERVAL, POLL_FOREGROUND_INTERVAL } from 'src/core/worker/worker-notification-listener';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { feature as featureService } from 'src/app/service-names';

/** @private */
export const useNotificationStore = () => Shopware.Store.get(notificationStoreId);
