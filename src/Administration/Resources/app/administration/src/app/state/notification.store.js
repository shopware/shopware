import { POLL_BACKGROUND_INTERVAL } from 'src/core/worker/worker-notification-listener';

const { Application, State } = Shopware;
const { debug } = Shopware.Utils;
const utils = Shopware.Utils;
const NOTIFICATION_LOAD_LIMIT = 50;

export function initializeUserNotifications() {
    if (Application.getApplicationRoot().$store) {
        Application.getApplicationRoot().$store.commit('notification/setNotificationsForCurrentUser');
        return;
    }
    State.get('notification').notifications = getNotificationsForUser();
}

function _getOriginalNotification(notificationId, state) {
    let originalNotification = state.notifications[notificationId];
    if (originalNotification === undefined) {
        originalNotification = Object.assign(
            {},
            state.notificationDefaults,
            {
                uuid: notificationId,
                timestamp: new Date(),
            },
        );
    }
    return originalNotification;
}

function _mergeNotificationUpdate(originalNotification, notificationUpdate) {
    return Object.assign(
        {},
        originalNotification,
        {
            visited: notificationUpdate.metadata ?
                (
                    JSON.stringify(originalNotification.metadata) ===
                    JSON.stringify(notificationUpdate.metadata)
                ) :
                originalNotification.visited,
        },
        notificationUpdate,
    );
}

function _getStorageKey() {
    const user = State.get('session').currentUser;

    if (!user) {
        return null;
    }

    const userId = user.id;
    if (!userId) {
        return null;
    }

    return `notifications#${userId}`;
}

export function getNotificationsForUser() {
    const storageKey = _getStorageKey();
    if (!storageKey) {
        return {};
    }

    const notificationsRaw = localStorage.getItem(storageKey);
    if (!notificationsRaw) {
        localStorage.setItem(storageKey, JSON.stringify({}));
        return {};
    }

    const notificationsAll = JSON.parse(notificationsRaw);
    const reverseIds = Object.keys(notificationsAll).reverse();
    const notifications = {};
    for (let i = Math.min(NOTIFICATION_LOAD_LIMIT, reverseIds.length) - 1; i >= 0; i -= 1) {
        const id = reverseIds[i];

        notifications[id] = {
            ...notificationsAll[id],
            timestamp: new Date(notificationsAll[id].timestamp),
        };
    }

    if (reverseIds.length > NOTIFICATION_LOAD_LIMIT) {
        _saveNotifications(notifications);
    }

    return notifications;
}

function _saveNotifications(notifications) {
    const storageKey = _getStorageKey();
    if (!storageKey) {
        return;
    }

    const storageNotifications = {};
    Object.keys(notifications).forEach((id) => {
        if (notifications[id].isLoading === false) {
            storageNotifications[id] = {
                ...notifications[id],
                timestamp: notifications[id].timestamp.toJSON(),
            };
        }
    });

    localStorage.setItem(storageKey, JSON.stringify(storageNotifications));
}

export default {
    namespaced: true,
    state: {
        notifications: {},
        growlNotifications: {},
        threshold: 5,
        workerProcessPollInterval: POLL_BACKGROUND_INTERVAL,
        notificationDefaults: {
            visited: false,
            metadata: {},
            isLoading: false,
        },
        growlNotificationDefaults: {
            system: false,
            variant: 'info', // success, info, warning, error
            autoClose: true,
            duration: 5000,
        },
    },

    getters: {
        getNotifications(state) {
            return Object.values(state.notifications).reverse();
        },

        getGrowlNotifications(state) {
            return Object.values(state.growlNotifications);
        },
    },

    mutations: {
        setThreshold(state, threshold = 5) {
            state.threshold = threshold;

            if (state.growlNotifications.length > state.threshold) {
                state.growlNotifications.splice(
                    threshold,
                    state.growlNotifications.length - state.threshold,
                );
            }
        },

        setWorkerProcessPollInterval(state, interval) {
            state.workerProcessPollInterval = interval;
        },

        setNotificationsForCurrentUser(state) {
            state.notifications = getNotificationsForUser();
        },

        clearNotificationsForCurrentUser(state) {
            state.notifications = {};

            const storageKey = _getStorageKey();
            if (!storageKey) {
                return;
            }

            localStorage.removeItem(storageKey);
        },

        clearGrowlNotificationsForCurrentUser(state) {
            state.growlNotifications = {};
        },

        setNotifications(state, notifications) {
            Object.keys(notifications).forEach((id) => {
                Application.view.setReactive(state.notifications, notifications[id].uuid, notifications[id]);
            });
        },

        upsertNotification(state, notificationUpdate) {
            const notification = state.notifications[notificationUpdate.uuid];
            if (notification !== undefined) {
                Object.assign(notification, notificationUpdate);
                return;
            }

            Application.view.setReactive(state.notifications, notificationUpdate.uuid, notificationUpdate);
            _saveNotifications(state.notifications);
        },

        removeNotification(state, notification) {
            Application.view.deleteReactive(state.notifications, notification.uuid);
            _saveNotifications(state.notifications);
        },

        setAllNotificationsVisited(state) {
            Object.keys(state.notifications).forEach((id) => {
                state.notifications[id].visited = true;
            });

            _saveNotifications(state.notifications);
        },

        upsertGrowlNotification(state, notificationUpdate) {
            const notification = state.growlNotifications[notificationUpdate.uuid];
            if (notification !== undefined) {
                Object.assign(notification, notificationUpdate);
                return;
            }

            Application.view.setReactive(state.growlNotifications, notificationUpdate.uuid, notificationUpdate);

            const growlKeys = Object.keys(state.growlNotifications);
            if (growlKeys.length > state.threshold) {
                Application.view.deleteReactive(state.growlNotifications, growlKeys[0]);
            }
        },

        removeGrowlNotification(state, notification) {
            Application.view.deleteReactive(state.growlNotifications, notification.uuid);
        },
    },

    actions: {
        createNotification({ state, commit, dispatch }, notification) {
            if (!notification.message) {
                debug.warn('NotificationStore', 'A message must be specified', notification);
                return null;
            }

            if (notification.growl === undefined || notification.growl === true) {
                dispatch('createGrowlNotification', notification);
            }

            delete notification.growl;
            const mergedNotification = Object.assign(
                {},
                state.notificationDefaults,
                notification,
                {
                    uuid: utils.createId(),
                    timestamp: new Date(),
                },
            );

            if (mergedNotification.variant === 'success') {
                return null;
            }

            commit('upsertNotification', mergedNotification);
            return mergedNotification.uuid;
        },

        createGrowlNotification({ state, commit }, notification) {
            const mergedNotification = Object.assign(
                {},
                state.growlNotificationDefaults,
                notification,
                {
                    uuid: utils.createId(),
                    timestamp: new Date(),
                },
            );

            delete mergedNotification.growl;
            commit('upsertGrowlNotification', mergedNotification);
            if (mergedNotification.autoClose) {
                setTimeout(() => {
                    commit('removeGrowlNotification', mergedNotification);
                }, mergedNotification.duration);
            }
        },

        updateNotification({ state, commit, dispatch }, notificationUpdate) {
            if (!notificationUpdate.uuid) {
                debug.warn('NotificationStore', 'Update to an notification must contain the uuid', notificationUpdate);
                return null;
            }

            const originalNotification = _getOriginalNotification(notificationUpdate.uuid, state);
            const mergedUpdate = _mergeNotificationUpdate(originalNotification, notificationUpdate);

            commit('upsertNotification', mergedUpdate);

            if (notificationUpdate.growl !== undefined &&
                notificationUpdate.growl === true) {
                dispatch('createGrowlNotification', mergedUpdate);
            }

            return originalNotification.uuid;
        },

        setAllNotificationsVisited({ commit }) {
            commit('setAllNotificationsVisited');
        },
    },
};
