/**
 * @sw-package framework
 *
 * Everything notification mixin needs from outside its mini-module.
 */
/** @private */
export { defineComponent } from 'vue';
/** @private */
export { useNotificationStore } from 'src/app/store/notification';
/** @private */
export type { NotificationType, NotificationVariant } from 'src/app/store/notification';
