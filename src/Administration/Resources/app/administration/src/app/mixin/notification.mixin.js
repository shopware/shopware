const { Mixin } = Shopware;

Mixin.register('notification', {
    methods: {
        createNotification(notification) {
            return Shopware.State.dispatch('notification/createNotification', notification);
        },

        createNotificationSuccess(config) {
            const notification = Object.assign({
                variant: 'success'
            }, config);

            this.createNotification(notification);
        },

        createNotificationInfo(config) {
            const notification = Object.assign({
                variant: 'info'
            }, config);

            this.createNotification(notification);
        },

        createNotificationWarning(config) {
            const notification = Object.assign({
                variant: 'warning'
            }, config);

            this.createNotification(notification);
        },

        createNotificationError(config) {
            const notification = Object.assign({
                variant: 'error'
            }, config);

            this.createNotification(notification);
        },

        createSystemNotificationSuccess(config) {
            const notification = Object.assign({
                variant: 'success',
                system: true
            }, config);

            this.createNotification(notification);
        },

        createSystemNotificationInfo(config) {
            const notification = Object.assign({
                variant: 'info',
                system: true
            }, config);

            this.createNotification(notification);
        },

        createSystemNotificationWarning(config) {
            const notification = Object.assign({
                variant: 'warning',
                system: true
            }, config);

            this.createNotification(notification);
        },

        createSystemNotificationError(config) {
            const notification = Object.assign({
                variant: 'error',
                system: true
            }, config);

            this.createNotification(notification);
        },

        createSystemNotification(config) {
            const notification = Object.assign({
                system: true
            }, config);

            this.createNotification(notification);
        }
    }
});
