import utils, { debug } from 'src/core/service/util.service';

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

        upsertNotification(state, notificationUpdate) {
            const notification = state.notifications.find(n => n.uuid === notificationUpdate.uuid);
            if (notification !== undefined) {
                Object.assign(notification, notificationUpdate);
                return;
            }

            state.notifications.unshift(notificationUpdate);
        },

        removeNotification(state, notification) {
            const index = state.notifications.findIndex(n => n.uuid === notification.uuid);
            if (index !== -1) {
                state.notifications.splice(index, 1);
            }
        },

        setAllNotificationsVisited(state) {
            state.notifications.forEach((notification) => {
                notification.visited = true;
            });
        },

        upsertGrowlNotification(state, notificationUpdate) {
            const notification = state.growlNotifications.find(n => n.uuid === notificationUpdate.uuid);
            if (notification !== undefined) {
                Object.assign(notification, notificationUpdate);
                return;
            }

            state.growlNotifications.push(notificationUpdate);
            if (state.growlNotifications.length > state.threshold) {
                state.growlNotifications.splice(0, 1);
            }
        },

        removeGrowlNotification(state, notification) {
            const index = state.growlNotifications.findIndex(n => n.uuid === notification.uuid);
            if (index !== -1) {
                state.growlNotifications.splice(index, 1);
            }
        }
    },

    actions: {
        createNotification({ state, commit }, notification) {
            if (!notification.message) {
                debug.warn('NotificationStore', 'A message must be specified', notification);
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

            commit('upsertNotification', mergedNotification);

            if (mergedNotification.growl) {
                commit('upsertGrowlNotification', mergedNotification);
                if (mergedNotification.autoClose) {
                    setTimeout(() => {
                        commit('removeGrowlNotification', mergedNotification);
                    }, mergedNotification.duration);
                }
            }

            return mergedNotification.uuid;
        },

        /**
         * Updates the notification with the given uuid in the payload. If growl is set to true and the notification
         * is not currently visible (as growl) it will show the notification as growl. Visited can also be set to
         * false to notify the user about the update. If the notification was already deleted by the user
         * it will be created again.
         *
         * @param notificationUpdate update payload
         * @returns {string} uuid
         */
        updateNotification({ state, commit }, notificationUpdate) {
            if (!notificationUpdate.uuid) {
                debug.warn('NotificationStore', 'Update to an notification must contain the uuid', notificationUpdate);
                return null;
            }

            const growlNotificationExists = state.growlNotifications.find(
                n => n.uuid === notificationUpdate.uuid
            ) !== undefined;
            let originalNotification = state.notifications.find(n => n.uuid === notificationUpdate.uuid);
            if (originalNotification === undefined) {
                originalNotification = Object.assign(
                    {},
                    state.defaults,
                    {
                        uuid: notificationUpdate.uuid,
                        timestamp: new Date()
                    }
                );
            }

            const mergedUpdate = Object.assign(
                {},
                originalNotification,
                notificationUpdate,
                {
                    growl: notificationUpdate.growl === undefined ? originalNotification.growl : notificationUpdate.growl
                }
            );

            commit('upsertNotification', mergedUpdate);
            if (growlNotificationExists) {
                commit('upsertGrowlNotification', mergedUpdate);
            }

            if (!growlNotificationExists &&
                notificationUpdate.growl !== undefined &&
                notificationUpdate.growl === true) {
                commit('upsertGrowlNotification', mergedUpdate);
                if (mergedUpdate.autoClose) {
                    setTimeout(() => {
                        commit('removeGrowlNotification', mergedUpdate);
                    }, mergedUpdate.duration);
                }
            }

            return originalNotification.uuid;
        },

        setAllNotificationsVisited({ commit }) {
            commit('setAllNotificationsVisited');
        }
    }
};
