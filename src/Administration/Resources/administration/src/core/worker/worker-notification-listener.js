import Axios from 'axios';
import ApiService from 'src/core/service/api.service';
import { Application } from 'src/core/shopware';

class WorkerNotificationListener {
    constructor(loginService, context) {
        this._messageQueueStatsService = this._getMessageQueueStatsService(loginService, context);
        this._isRunning = false;
        this._interval = 5000;
        this._timeoutId = null;
        this._applicationRoot = null;
        this._thumbnailNotificationId = null;
    }

    _getMessageQueueStatsService(loginService, context) {
        const baseURL = process.env.NODE_ENV !== 'production' ?
            `${window.location.origin}${context.apiResourcePath}` :
            context.apiResourcePath;
        const client = Axios.create({
            baseURL: baseURL
        });

        return new ApiService(client, loginService, 'message-queue-stats');
    }

    start(interval = 5000) {
        this._interval = interval;
        this._isRunning = true;
        this._timeoutId = setTimeout(this._checkQueue.bind(this), this._interval);
    }

    terminate() {
        this._isRunning = false;
        if (this._timeoutId !== null) {
            clearTimeout(this._timeoutId);
            this._timeoutId = null;
        }
    }

    _checkQueue() {
        this._messageQueueStatsService.getList({}).then((res) => {
            this._timeoutId = null;
            this._manageNotifications(res.data);

            if (this._isRunning) {
                this._timeoutId = setTimeout(this._checkQueue.bind(this), this._interval);
            }
        });
    }

    _manageNotifications(queueStats) {
        const thumbnailQueue = queueStats.find(
            (q) => q.name === 'Shopware\\Core\\Content\\Media\\Message\\GenerateThumbnailsMessage'
        );
        if (!thumbnailQueue) {
            return;
        }

        const notification = {
            title: this._getApplicationRootReference().$tc(
                'global.notification-center.worker-listener.thumbnailGeneration.title'
            ),
            message: this._getApplicationRootReference().$tc(
                'global.notification-center.worker-listener.thumbnailGeneration.message',
                thumbnailQueue.size
            ),
            variant: 'info',
            metadata: {
                size: thumbnailQueue.size
            },
            growl: false,
            isLoading: true
        };

        if (thumbnailQueue.size > 0 && this._thumbnailNotificationId === null) {
            this._getApplicationRootReference().$store.dispatch(
                'notification/createNotification',
                notification
            ).then((uuid) => {
                this._thumbnailNotificationId = uuid;
            });
            return;
        }

        if (this._thumbnailNotificationId !== null) {
            notification.uuid = this._thumbnailNotificationId;
            if (thumbnailQueue.size === 0) {
                this._thumbnailNotificationId = null;
                notification.title = this._getApplicationRootReference().$t(
                    'global.notification-center.worker-listener.thumbnailGeneration.titleSuccess'
                );
                notification.message = this._getApplicationRootReference().$t(
                    'global.notification-center.worker-listener.thumbnailGeneration.messageSuccess'
                );
                notification.isLoading = false;
            }
            this._getApplicationRootReference().$store.dispatch(
                'notification/updateNotification',
                notification
            );
        }
    }

    _getApplicationRootReference() {
        if (!this._applicationRoot) {
            this._applicationRoot = Application.getApplicationRoot();
        }

        return this._applicationRoot;
    }
}

export default WorkerNotificationListener;
