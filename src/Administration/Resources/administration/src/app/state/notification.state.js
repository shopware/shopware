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
        createNotification({ commit }, item) {
            const defaults = {
                system: false,
                variant: 'info',
                uuid: utils.createId(),
                autoClose: true,
                duration: 5000
            };

            if (!item.message) {
                utils.warn('StateNotification', 'A message must be specified', item);
                return Promise.reject(item);
            }

            const notification = Object.assign({}, defaults, item);

            commit('createNotification', notification);

            if (!notification.autoClose) {
                return Promise.resolve(notification);
            }

            return new Promise((resolve) => {
                setTimeout(() => {
                    commit('removeNotification', notification.uuid);
                    resolve(notification);
                }, notification.duration);
            });
        }
    },

    mutations: {
        createNotification(state, notification) {
            state.notifications = [...state.notifications, notification];
        },

        removeNotification(state, uuid) {
            if (!utils.isString(uuid)) {
                state.notifications.splice(uuid, 1);
                return;
            }

            state.notifications = state.notifications.filter((item) => {
                return item.uuid !== uuid;
            });
        }
    }

});
