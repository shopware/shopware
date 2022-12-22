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
    system?: string,
    [key: string]: string | undefined,
}

Mixin.register('notification', {
    methods: {
        createNotification(notification: notification) {
            return Shopware.State.dispatch('notification/createNotification', notification);
        },

        createNotificationSuccess(config: notification): void {
            const notification = Object.assign({
                variant: 'success',
                title: this.$tc('global.default.success'),
            }, config);

            void this.createNotification(notification);
        },

        createNotificationInfo(config: notification): void {
            const notification = Object.assign({
                variant: 'info',
                title: this.$tc('global.default.info'),
            }, config);

            void this.createNotification(notification);
        },

        createNotificationWarning(config: notification): void {
            const notification = Object.assign({
                variant: 'warning',
                title: this.$tc('global.default.warning'),
            }, config);

            void this.createNotification(notification);
        },

        createNotificationError(config: notification): void {
            const notification = Object.assign({
                variant: 'error',
                title: this.$tc('global.default.error'),
            }, config);

            void this.createNotification(notification);
        },

        createSystemNotificationSuccess(config: notification): void {
            const notification = Object.assign({
                variant: 'success',
                system: true,
            }, config);

            void this.createNotification(notification);
        },

        createSystemNotificationInfo(config: notification): void {
            const notification = Object.assign({
                variant: 'info',
                system: true,
            }, config);

            void this.createNotification(notification);
        },

        createSystemNotificationWarning(config: notification): void {
            const notification = Object.assign({
                variant: 'warning',
                system: true,
            }, config);

            void this.createNotification(notification);
        },

        createSystemNotificationError(config: notification): void {
            const notification = Object.assign({
                variant: 'error',
                system: true,
            }, config);

            void this.createNotification(notification);
        },

        createSystemNotification(config: notification): void {
            const notification = Object.assign({
                system: true,
            }, config);

            void this.createNotification(notification);
        },
    },
});
