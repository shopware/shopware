import { State } from 'src/core/shopware';

/**
 * @module app/state/notification
 */
State.register('notification', {
    namespaced: true,
    strict: true,

    state() {
        return {
            notifications: []
        };
    },

    mutations: {
        createNotification(state, notification) {
            if (!notification.text) {
                throw new Error('A text must be specified');
            }

            const notificationObject = {
                title: notification.title,
                text: notification.text,
                system: notification.system ? notification.system : false,
                variant: notification.variant ? notification.variant : 'info'
            };

            state.notifications.push(notificationObject);
        },

        removeNotification(state, index) {
            state.notifications.splice(1, index);
        }
    }

});
