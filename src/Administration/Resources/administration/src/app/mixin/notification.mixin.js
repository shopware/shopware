import { Mixin, State } from 'src/core/shopware';

Mixin.register('notification', {

    computed: {
        notificationStore() {
            return State.getStore('notification');
        }
    },

    methods: {
        createNotificationSuccess(config) {
            const notification = Object.assign({
                variant: 'success'
            }, config);

            this.notificationStore.createNotification(notification);
        },

        createNotificationInfo(config) {
            const notification = Object.assign({
                variant: 'info'
            }, config);

            this.notificationStore.createNotification(notification);
        },

        createNotificationWarning(config) {
            const notification = Object.assign({
                variant: 'warning'
            }, config);

            this.notificationStore.createNotification(notification);
        },

        createNotificationError(config) {
            const notification = Object.assign({
                variant: 'error'
            }, config);

            this.notificationStore.createNotification(notification);
        },

        createSystemNotificationSuccess(config) {
            const notification = Object.assign({
                variant: 'success',
                system: true
            }, config);

            this.notificationStore.createNotification(notification);
        },

        createSystemNotificationInfo(config) {
            const notification = Object.assign({
                variant: 'info',
                system: true
            }, config);

            this.notificationStore.createNotification(notification);
        },

        createSystemNotificationWarning(config) {
            const notification = Object.assign({
                variant: 'warning',
                system: true
            }, config);

            this.notificationStore.createNotification(notification);
        },

        createSystemNotificationError(config) {
            const notification = Object.assign({
                variant: 'error',
                system: true
            }, config);

            this.notificationStore.createNotification(notification);
        },

        createNotification(config) {
            this.notificationStore.createNotification(config);
        },

        createSystemNotification(config) {
            const notification = Object.assign({
                system: true
            }, config);

            this.notificationStore.createNotification(notification);
        }
    }
});
