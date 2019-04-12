import utils from 'src/core/service/util.service';

export default {
    namespaced: true,
    state: {
        notifications: [],
        threshold: 5,
        defaults: {
            system: false,
            variant: 'info', // success, info, warning, error
            autoClose: true,
            duration: 5000
        }
    },

    mutations: {
        setThreshold(state, threshold = 5) {
            state.threshold = threshold;

            if (state.notifications.length > state.threshold) {
                state.notifications.splice(
                    threshold,
                    state.notifications.length - state.threshold
                );
            }
        },

        setDefaults(state, { system = false, variant = 'info', autoClose = true, duration = 5000 } = {}) {
            state.defaults.system = system;
            state.defaults.variant = variant;
            state.defaults.autoClose = autoClose;
            state.defaults.duration = duration;
        },

        pushNotification(state, notification) {
            state.notifications.push(notification);

            if (state.notifications.length > state.threshold) {
                state.notifications.splice(0, 1);
            }
        },

        removeNotification(state, notification) {
            const index = state.notifications.findIndex(n => n === notification);
            if (index !== -1) {
                state.notifications.splice(index, 1);
            }
        }
    },

    actions: {
        createNotification({ state, commit }, notification) {
            if (!notification.message) {
                utils.warn('NotificationStore', 'A message must be specified', notification);
                return;
            }

            const mergedNotification = Object.assign(
                {},
                state.defaults,
                notification,
                {
                    uuid: utils.createId()
                }
            );

            commit('pushNotification', mergedNotification);
            if (mergedNotification.autoClose) {
                setTimeout(() => {
                    commit('removeNotification', mergedNotification);
                }, mergedNotification.duration);
            }
        }
    }
};
