/**
 * @package admin
 */

const { Service } = Shopware;
const READ_NOTIFICATION = 'notification.lastReadAt';

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
export default class AdminNotificationWorker {
    constructor() {
        this._notificationService = Service('notificationsService');
        this._userConfigService = Service('userConfigService');
        this._notiticationInterval = 5000;
        this._notiticationTimeoutId = null;
        this._timestamp = null;
        this._limit = 5;
    }

    start() {
        if (!Shopware.Context.app.config.adminWorker.enableNotificationWorker) {
            return;
        }

        this.fetchUserConfig().then(() => {
            this.loadNotifications();
        });
    }

    loadNotifications() {
        this._notificationService.fetchNotifications(this._limit, this._timestamp).then(({ notifications, timestamp }) => {
            notifications.forEach((notification) => {
                const { status, message } = notification;
                this.createNotification(status, message);
            });

            if (timestamp) {
                this._timestamp = timestamp;
                this._userConfigService.upsert({ [READ_NOTIFICATION]: { timestamp } });
            }
        }).catch((error) => {
            this.createNotification('error', error.message);
        });

        this._notiticationTimeoutId = setTimeout(() => {
            this.loadNotifications();
        }, this._notiticationInterval);
    }

    terminate() {
        if (this._notiticationTimeoutId) {
            clearTimeout(this._notiticationTimeoutId);
            this._notiticationTimeoutId = null;
        }
    }

    createNotification(variant, message) {
        Shopware.State.dispatch('notification/createNotification', {
            variant,
            message,
        });
    }

    async fetchUserConfig() {
        await this._userConfigService.search([READ_NOTIFICATION]).then((response) => {
            const value = response.data[READ_NOTIFICATION];

            if (value) {
                this._timestamp = value.timestamp;
            }
        });
    }
}
