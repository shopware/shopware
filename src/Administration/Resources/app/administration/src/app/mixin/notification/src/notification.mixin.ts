/**
 * @sw-package framework
 */
import { defineComponent, type NotificationType, type NotificationVariant, useNotificationStore } from '../dependencies';

/**
 * @public
 *
 * Duplicated in `src/app/composables/use-notification`; change both together.
 */
export default defineComponent({
    methods: {
        createNotification(notification: NotificationType): string | null {
            return useNotificationStore().createNotification(notification);
        },

        createNotificationSuccess(config: NotificationType): void {
            const notification = {
                variant: 'success' as NotificationVariant,
                title: 'global.default.success',
                ...config,
            };

            void this.createNotification(notification);
        },

        createNotificationInfo(config: NotificationType): void {
            const notification = {
                variant: 'info' as NotificationVariant,
                title: 'global.default.info',
                ...config,
            };

            void this.createNotification(notification);
        },

        createNotificationWarning(config: NotificationType): void {
            const notification = {
                variant: 'warning' as NotificationVariant,
                title: 'global.default.warning',
                ...config,
            };

            void this.createNotification(notification);
        },

        createNotificationError(config: NotificationType): void {
            const notification = {
                variant: 'error' as NotificationVariant,
                title: 'global.default.error',
                ...config,
            };

            void this.createNotification(notification);
        },

        createSystemNotificationSuccess(config: NotificationType): void {
            const notification = {
                variant: 'success' as NotificationVariant,
                system: true,
                ...config,
            };

            void this.createNotification(notification);
        },

        createSystemNotificationInfo(config: NotificationType): void {
            const notification = {
                variant: 'info' as NotificationVariant,
                system: true,
                ...config,
            };

            void this.createNotification(notification);
        },

        createSystemNotificationWarning(config: NotificationType): void {
            const notification = {
                variant: 'warning' as NotificationVariant,
                system: true,
                ...config,
            };

            void this.createNotification(notification);
        },

        createSystemNotificationError(config: NotificationType): void {
            const notification = {
                variant: 'error' as NotificationVariant,
                system: true,
                ...config,
            };

            void this.createNotification(notification);
        },

        createSystemNotification(config: NotificationType): void {
            const notification = { system: true, ...config };

            void this.createNotification(notification);
        },
    },
});
