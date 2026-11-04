/**
 * @sw-package framework
 *
 * The `notification` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/notification`.
 */
/**
 * @public
 * @export-namespace stores
 */
export { notificationStoreId, useNotificationStore } from './src/notification.store';

/** @public */
export type { NotificationVariant, NotificationType, NotificationStore } from './src/notification.store';
