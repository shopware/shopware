import utils from 'src/core/service/util.service';

export default {
    namespaced: true,
    state: {
        notifications: [],
        growlNotifications: [],
        threshold: 5,
        defaults: {
            system: false,
            variant: 'info', // success, info, warning, error
            autoClose: true,
            duration: 5000,
            growl: true,
            visited: false
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
            state.notifications.unshift(notification);
        },

        removeNotification(state, notification) {
            const index = state.notifications.findIndex(n => n === notification);
            if (index !== -1) {
                state.notifications.splice(index, 1);
            }
        },

        setAllNotificationsVisited(state) {
            state.notifications.forEach((notification) => {
                notification.visited = true;
            });
        },

        pushGrowlNotification(state, notification) {
            state.growlNotifications.push(notification);

            if (state.growlNotifications.length > state.threshold) {
                state.growlNotifications.splice(0, 1);
            }
        },

        removeGrowlNotification(state, notification) {
            const index = state.growlNotifications.findIndex(n => n === notification);
            if (index !== -1) {
                state.growlNotifications.splice(index, 1);
            }
        }
    },

    actions: {
        createNotification({ state, commit }, notification) {
            if (!notification.message) {
                utils.warn('NotificationStore', 'A message must be specified', notification);
                return null;
            }

            const mergedNotification = Object.assign(
                {},
                state.defaults,
                notification,
                {
                    uuid: utils.createId(),
                    timestamp: new Date()
                }
            );

            commit('pushNotification', mergedNotification);

            if (mergedNotification.growl) {
                commit('pushGrowlNotification', mergedNotification);
                if (mergedNotification.autoClose) {
                    setTimeout(() => {
                        commit('removeGrowlNotification', mergedNotification);
                    }, mergedNotification.duration);
                }
            }

            return mergedNotification.uuid;
        },

        setAllNotificationsVisited({ commit }) {
            commit('setAllNotificationsVisited');
        }
    }
};
