import { Mixin } from 'src/core/shopware';

Mixin.register('notification', {

    methods: {
        createNotificationSuccess(config) {
            const notification = Object.assign({}, {
                variant: 'success'
            }, config);

            this.$store.dispatch('notification/createNotification', notification);
        },

        createNotificationInfo(config) {
            const notification = Object.assign({}, {
                variant: 'info'
            }, config);

            this.$store.dispatch('notification/createNotification', notification);
        },

        createNotificationWarning(config) {
            const notification = Object.assign({}, {
                variant: 'warning'
            }, config);

            this.$store.dispatch('notification/createNotification', notification);
        },

        createNotificationError(config) {
            const notification = Object.assign({}, {
                variant: 'error'
            }, config);

            this.$store.dispatch('notification/createNotification', notification);
        },

        createSystemNotificationSuccess(config) {
            const notification = Object.assign({}, {
                variant: 'success',
                system: true
            }, config);

            this.$store.dispatch('notification/createNotification', notification);
        },

        createSystemNotificationInfo(config) {
            const notification = Object.assign({}, {
                variant: 'info',
                system: true
            }, config);

            this.$store.dispatch('notification/createNotification', notification);
        },

        createSystemNotificationWarning(config) {
            const notification = Object.assign({}, {
                variant: 'warning',
                system: true
            }, config);

            this.$store.dispatch('notification/createNotification', notification);
        },

        createSystemNotificationError(config) {
            const notification = Object.assign({}, {
                variant: 'error',
                system: true
            }, config);

            this.$store.dispatch('notification/createNotification', notification);
        },

        createNotification(config) {
            this.$store.dispatch('notification/createNotification', config);
        },

        createSystemNotification(config) {
            const notification = Object.assign({}, {
                system: true
            }, config);

            this.$store.dispatch('notification/createNotification', notification);
        }
    }

});
