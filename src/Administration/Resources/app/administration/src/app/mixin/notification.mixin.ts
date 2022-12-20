/**
 * @package admin
 */

const { Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type NotificationType = 'info' | 'warning' | 'error' | 'success';

interface notification {
    variant?: NotificationType,
    title?: string,
    message?: string,
    system?: boolean,

    [key: string]: string | boolean | undefined,
}

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Mixin.register('notification', {
    methods: {
        createNotification(notification: notification) {
            return Shopware.State.dispatch('notification/createNotification', notification);
        },

        createNotificationSuccess(config: notification): void {
            const notification = {
                variant: 'success' as NotificationType,
                title: this.$tc('global.default.success'),
                ...config,
            };

            void this.createNotification(notification);
        },

        createNotificationInfo(config: notification): void {
            const notification = {
                variant: 'info' as NotificationType,
                title: this.$tc('global.default.info'),
                ...config,
            };

            void this.createNotification(notification);
        },

        createNotificationWarning(config: notification): void {
            const notification = {
                variant: 'warning' as NotificationType,
                title: this.$tc('global.default.warning'),
                ...config,
            };

            void this.createNotification(notification);
        },

        createNotificationError(config: notification): void {
            const notification = {
                variant: 'error' as NotificationType,
                title: this.$tc('global.default.error'),
                ...config,
            };

            void this.createNotification(notification);
        },

        createSystemNotificationSuccess(config: notification): void {
            const notification = {
                variant: 'success' as NotificationType,
                system: true,
                ...config,
            };

            void this.createNotification(notification);
        },

        createSystemNotificationInfo(config: notification): void {
            const notification = {
                variant: 'info' as NotificationType,
                system: true,
                ...config,
            };

            void this.createNotification(notification);
        },

        createSystemNotificationWarning(config: notification): void {
            const notification = {
                variant: 'warning' as NotificationType,
                system: true,
                ...config,
            };

            void this.createNotification(notification);
        },

        createSystemNotificationError(config: notification): void {
            const notification = {
                variant: 'error' as NotificationType,
                system: true,
                ...config,
            };

            void this.createNotification(notification);
        },

        createSystemNotification(config: notification): void {
            const notification = { system: true, ...config };

            void this.createNotification(notification);
        },
    },
});
