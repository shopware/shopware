/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type { NotificationType, NotificationVariant } from '../store/notification.store';

const { Mixin } = Shopware;

/**
 * @private
 */
export default Mixin.register(
    'notification',
    defineComponent({
        methods: {
            createNotification(notification: NotificationType): string | null {
                return Shopware.Store.get('notification').createNotification(notification);
            },

            createNotificationSuccess(config: NotificationType): void {
                const notification = {
                    variant: 'success' as NotificationVariant,
                    title: this.$tc('global.default.success'),
                    ...config,
                };

                void this.createNotification(notification);
            },

            createNotificationInfo(config: NotificationType): void {
                const notification = {
                    variant: 'info' as NotificationVariant,
                    title: this.$tc('global.default.info'),
                    ...config,
                };

                void this.createNotification(notification);
            },

            createNotificationWarning(config: NotificationType): void {
                const notification = {
                    variant: 'warning' as NotificationVariant,
                    title: this.$tc('global.default.warning'),
                    ...config,
                };

                void this.createNotification(notification);
            },

            createNotificationError(config: NotificationType): void {
                const notification = {
                    variant: 'error' as NotificationVariant,
                    title: this.$tc('global.default.error'),
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
    }),
);
