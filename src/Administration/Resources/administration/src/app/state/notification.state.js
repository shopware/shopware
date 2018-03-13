import { State } from 'src/core/shopware';
import utils from 'src/core/service/util.service';

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

    actions: {
        createNotification({ commit }, notification) {
            const defaultConfig = {
                system: false,
                variant: 'info',
                uuid: utils.createId(),
                autoDismiss: true,
                duration: 5000
            };

            if (!notification.text) {
                utils.warn(
                    'StateNotification',
                    'A text must be specified',
                    notification
                );
                return Promise.reject(notification);
            }

            const notificationObject = Object.assign({}, defaultConfig, notification);

            commit('createNotification', notificationObject);

            if (!notificationObject.autoDismiss) {
                return Promise.resolve(notificationObject);
            }

            return new Promise((resolve) => {
                setTimeout(() => {
                    commit('removeNotification', 0);
                    resolve(notificationObject);
                }, notificationObject.duration);
            });
        }
    },

    mutations: {
        createNotification(state, notification) {
            state.notifications = [...state.notifications, notification];
        },

        removeNotification(state, index) {
            if (index > -1) {
                state.notifications.splice(index, 1);
            }
        }
    }

});
