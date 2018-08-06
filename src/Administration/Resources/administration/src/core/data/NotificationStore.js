/**
 * @module core/data/NotificationStore
 */
import utils from 'src/core/service/util.service';
import types from 'src/core/service/utils/types.utils';
import { warn } from 'src/core/service/utils/debug.utils';

class NotificationStore {
    constructor() {
        this.notifications = [];

        this.defaults = {
            system: false,
            variant: 'info', // success, info, warning, error
            autoClose: true,
            duration: 5000
        };
    }

    /**
     * Create a new notification.
     *
     * @param {Object} config
     * @returns {Promise<T>}
     */
    createNotification(config) {
        if (!config.message) {
            warn('NotificationStore', 'A message must be specified', config);
            return Promise.reject(config);
        }

        const notification = Object.assign({ uuid: utils.createId() }, this.defaults, config);

        this.addNotification(notification);

        if (!notification.autoClose) {
            return Promise.resolve(notification);
        }

        return new Promise((resolve) => {
            setTimeout(() => {
                this.removeNotification(notification.uuid);
                resolve(notification);
            }, notification.duration);
        });
    }

    /**
     * Add a notification object to the store.
     *
     * @param {Object} notification
     */
    addNotification(notification) {
        this.notifications.push(notification);

        if (this.notifications.length > 5) {
            this.removeNotification();
        }
    }

    /**
     * Remove a notification from the store.
     *
     * @param uuid
     */
    removeNotification(uuid = 0) {
        if (!types.isString(uuid)) {
            this.notifications.splice(uuid, 1);
            return;
        }

        this.notifications.splice(this.notifications.findIndex(item => item.uuid === uuid), 1);
    }
}

export default NotificationStore;
