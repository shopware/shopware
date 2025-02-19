import { POLL_BACKGROUND_INTERVAL } from 'src/core/worker/worker-notification-listener';

/**
 * @sw-package framework
 */

/**
 * @private
 */
export type NotificationVariant = 'neutral' | 'info' | 'attention' | 'critical' | 'positive';

/**
 * @private
 */
export interface NotificationType {
    variant?: NotificationVariant;
    title?: string;
    message?: string;
    system?: boolean;
    growl?: boolean;
    isLoading?: boolean;
    metadata?: { size?: number };
    uuid?: string;
    duration?: number;
    autoClose?: boolean;
    visited?: boolean;
    timestamp?: Date;
    actions?: {
        label: string;
        disabled?: boolean;
        route?: string;
        method?: () => void;
    }[];

    [key: string]: string | boolean | object | number | undefined;
}

const { debug } = Shopware.Utils;
const utils = Shopware.Utils;
const NOTIFICATION_LOAD_LIMIT = 50;
const notificationDefaults: NotificationType = {
    visited: false,
    metadata: {},
    isLoading: false,
};

const growlNotificationDefaults: NotificationType = {
    system: false,
    variant: 'info',
    autoClose: true,
    duration: 5000,
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function initializeUserNotifications() {
    if (Shopware.Store) {
        Shopware.Store.get('notification').notifications = getNotificationsForUser();
    }
}

// eslint-disable-next-line no-use-before-define
function _getOriginalNotification(notificationId: string, store: NotificationStore) {
    let originalNotification = store.notifications[notificationId];
    if (originalNotification === undefined) {
        originalNotification = {
            ...notificationDefaults,
            uuid: notificationId,
            timestamp: new Date(),
        };
    }
    return originalNotification;
}

function _mergeNotificationUpdate(originalNotification: NotificationType, notificationUpdate: NotificationType) {
    return {
        ...originalNotification,
        visited: notificationUpdate.metadata
            ? JSON.stringify(originalNotification.metadata) === JSON.stringify(notificationUpdate.metadata)
            : originalNotification.visited,
        ...notificationUpdate,
    };
}

function _getStorageKey() {
    const user = Shopware.Store.get('session').currentUser;

    if (!user) {
        return null;
    }

    const userId = user.id;
    if (!userId) {
        return null;
    }

    return `notifications#${userId}`;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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

    const notificationsAll = JSON.parse(notificationsRaw) as Record<string, NotificationType>;
    const reverseIds = Object.keys(notificationsAll).reverse();
    const notifications: Record<string, NotificationType> = {};
    for (let i = Math.min(NOTIFICATION_LOAD_LIMIT, reverseIds.length) - 1; i >= 0; i -= 1) {
        const id = reverseIds[i];

        notifications[id] = {
            ...notificationsAll[id],
            timestamp: new Date(notificationsAll[id]?.timestamp ?? Date.now()),
        };
    }

    if (reverseIds.length > NOTIFICATION_LOAD_LIMIT) {
        _saveNotifications(notifications);
    }

    return notifications;
}

function _saveNotifications(notifications: Record<string, NotificationType>) {
    const storageKey = _getStorageKey();
    if (!storageKey) {
        return;
    }

    const storageNotifications: Record<string, unknown> = {};
    Object.keys(notifications).forEach((id) => {
        if (!notifications[id].isLoading) {
            storageNotifications[id] = {
                ...notifications[id],
                timestamp: notifications[id]?.timestamp?.toJSON(),
            } as unknown;
        }
    });

    localStorage.setItem(storageKey, JSON.stringify(storageNotifications));
}

const notificationStore = Shopware.Store.register({
    id: 'notification',

    state: () => ({
        notifications: {} as Record<string, NotificationType>,
        growlNotifications: {} as Record<string, NotificationType>,
        threshold: 5,
        workerProcessPollInterval: POLL_BACKGROUND_INTERVAL,
    }),

    actions: {
        setThreshold(threshold = 5) {
            this.threshold = threshold;

            const growlKeys = Object.keys(this.growlNotifications).slice(0, this.threshold);
            const slicedGrowlNotifications: Record<string, NotificationType> = {};

            growlKeys.forEach((key) => {
                slicedGrowlNotifications[key] = this.growlNotifications[key];
            });

            this.growlNotifications = slicedGrowlNotifications;
        },

        clearNotificationsForCurrentUser() {
            this.notifications = {};

            const storageKey = _getStorageKey();
            if (!storageKey) {
                return;
            }

            localStorage.removeItem(storageKey);
        },

        clearGrowlNotificationsForCurrentUser() {
            this.growlNotifications = {};
        },

        setNotifications(notifications: Record<string, NotificationType & { uuid: string }>) {
            Object.keys(notifications).forEach((id) => {
                const uuid = notifications[id].uuid;
                // in case this is used from JS, we need to check if the uuid is set
                if (uuid) {
                    this.notifications[uuid] = notifications[id];
                }
            });
        },

        upsertNotification(notificationUpdate: Partial<NotificationType & { uuid: string }>) {
            if (!notificationUpdate.uuid) {
                debug.warn('NotificationStore', 'A notification must contain a uuid', notificationUpdate);
                return;
            }
            this.notifications[notificationUpdate.uuid] = {
                ...this.notifications[notificationUpdate.uuid],
                ...notificationUpdate,
            };
            _saveNotifications(this.notifications);
        },

        removeNotification(notification: NotificationType & { uuid: string }) {
            if (!notification.uuid) {
                debug.warn('NotificationStore', 'A notification must contain a uuid', notification);
                return;
            }
            delete this.notifications[notification.uuid];
            _saveNotifications(this.notifications);
        },

        setAllNotificationsVisited() {
            Object.keys(this.notifications).forEach((id) => {
                this.notifications[id].visited = true;
            });

            _saveNotifications(this.notifications);
        },

        upsertGrowlNotification(notificationUpdate: Partial<NotificationType>) {
            if (!notificationUpdate.uuid) {
                debug.warn('NotificationStore', 'A notification must contain a uuid', notificationUpdate);
                return;
            }

            this.growlNotifications[notificationUpdate.uuid] = {
                ...this.growlNotifications[notificationUpdate.uuid],
                ...notificationUpdate,
            };

            const growlKeys = Object.keys(this.growlNotifications);
            if (growlKeys.length > this.threshold) {
                delete this.growlNotifications[growlKeys[0]];
            }
        },

        removeGrowlNotification(notification: NotificationType) {
            if (!notification.uuid) {
                debug.warn('NotificationStore', 'A notification must contain a uuid', notification);
                return;
            }
            delete this.growlNotifications[notification.uuid];
        },

        createNotification(notification: NotificationType) {
            if (!notification.message) {
                debug.warn('NotificationStore', 'A message must be specified', notification);
                return null;
            }

            if (notification.growl === undefined || notification.growl) {
                this.createGrowlNotification(notification);
            }

            delete notification.growl;
            const mergedNotification = {
                ...notificationDefaults,
                uuid: utils.createId(),
                timestamp: new Date(),
                ...notification,
            };

            // @ts-expect-error - fallback for success variant
            if (mergedNotification.variant === 'success' || mergedNotification.variant === 'positive') {
                return null;
            }

            this.upsertNotification(mergedNotification);
            return mergedNotification.uuid;
        },

        createGrowlNotification(notification: NotificationType) {
            const mergedNotification = {
                ...growlNotificationDefaults,
                ...notification,
                uuid: utils.createId(),
                timestamp: new Date(),
            };

            delete mergedNotification.growl;
            this.upsertGrowlNotification(mergedNotification);
            if (mergedNotification.autoClose) {
                setTimeout(() => {
                    this.removeGrowlNotification(mergedNotification);
                }, mergedNotification.duration);
            }
        },

        updateNotification(notificationUpdate: NotificationType) {
            if (!notificationUpdate.uuid) {
                debug.warn('NotificationStore', 'Update to an notification must contain the uuid', notificationUpdate);
                return null;
            }

            const originalNotification = _getOriginalNotification(notificationUpdate.uuid, this);
            const mergedUpdate = _mergeNotificationUpdate(originalNotification, notificationUpdate);

            this.upsertNotification(mergedUpdate);

            if (notificationUpdate.growl !== undefined && notificationUpdate.growl === true) {
                this.createGrowlNotification(mergedUpdate);
            }

            return originalNotification.uuid;
        },
    },
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type NotificationStore = ReturnType<typeof notificationStore>;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default notificationStore;
