import utils from 'src/core/service/util.service';
import { warn } from 'src/core/service/utils/debug.utils';

class NotificationStore {
    constructor() {
        this._threshold = 5;
        this._notifications = [];

        this._defaults = {
            system: false,
            variant: 'info', // success, info, warning, error
            autoClose: true,
            duration: 5000
        };
    }

    /**
     * Creates a new notification and adds itself to the notification collection.
     *
     * @param {Object} config - Notification declaration
     * @returns {Promise<void>}
     */
    createNotification(config) {
        if (!config.message) {
            warn('NotificationStore', 'A message must be specified', config);
            return Promise.reject(config);
        }

        config = Object.assign({}, this.defaults, config, {
            uuid: utils.createId()
        });

        return new Promise((resolve) => {
            config.timeoutId = null;

            if (config.autoClose) {
                config.timeoutId = setTimeout(() => {
                    this.removeNotification(config);
                    resolve(config);
                }, config.duration);
            }

            this.addNotification(config);

            if (!config.autoClose) {
                resolve(config);
            }
        });
    }

    /**
     * Adds a notification to the notification collection
     *
     * @param {Object} config - Notification declaration
     * @returns {boolean}
     */
    addNotification(config) {
        if (this.findIndexOfNotification(config)) {
            return false;
        }

        this._notifications.push(config);

        if (this._notifications.length > this.threshold) {
            const lastNotification = this.getFirstNotificationFromCollection();
            this.removeNotification(lastNotification);
        }
        return true;
    }

    /**
     * Removes a notification from the collection and clears the timeout when the notification should autoClose.
     *
     * @param {Object} config - Notification declaration
     * @returns {boolean}
     */
    removeNotification(config) {
        if (!this.findIndexOfNotification(config)) {
            return false;
        }

        if (config.timeoutId) {
            clearTimeout(config.timeoutId);
        }
        const index = this._notifications.findIndex((item) => {
            return config.uuid === item.uuid;
        });
        this._notifications.splice(index, 1);

        return true;
    }

    /**
     * Returns the first element from the notification collection
     *
     * @returns {Object} Notification declaration
     */
    getFirstNotificationFromCollection() {
        return this._notifications[0];
    }

    /**
     * Find a notification in the notification collection using the uuid of the notifcations.
     *
     * @param {Object} config - Notification declaration
     * @returns {void|Object}
     */
    findIndexOfNotification(config) {
        return this._notifications.find((item) => {
            return config.uuid === item.uuid;
        });
    }

    get notifications() {
        return this._notifications;
    }

    get defaults() {
        return this._defaults;
    }

    set defaults(defaults) {
        this._defaults = defaults;
        return true;
    }

    get threshold() {
        return this._threshold;
    }
}

export default NotificationStore;
