import Axios from 'axios';
import ApiService from 'src/core/service/api.service';
import { Application } from 'src/core/shopware';

export const POLL_BACKGROUND_INTERVAL = 30000;
export const POLL_FOREGROUND_INTERVAL = 5000;

class WorkerNotificationListener {
    constructor(loginService, context) {
        this._messageQueueStatsService = this._getMessageQueueStatsService(loginService, context);
        this._isRunning = false;
        this._isRequestRunning = false;
        this._interval = POLL_BACKGROUND_INTERVAL;
        this._isIntervalWatcherSetup = false;
        this._timeoutId = null;
        this._applicationRoot = null;
        this._thumbnailNotificationId = null;
    }

    start() {
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

    setupIntervalWatcher() {
        if (this._isIntervalWatcherSetup) {
            return;
        }

        this._getApplicationRootReference().$store.watch((state) => {
            return state.notification.workerProcessPollInterval;
        }, this._onPollIntervalChanged.bind(this));

        this._isIntervalWatcherSetup = true;
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

    _checkQueue() {
        this.setupIntervalWatcher();
        this._isRequestRunning = true;
        this._messageQueueStatsService.getList({}).then((res) => {
            this._isRequestRunning = false;
            this._timeoutId = null;
            this._manageNotifications(res.data);

            if (this._isRunning) {
                this._interval = this._getApplicationRootReference().$store.state.notification.workerProcessPollInterval;
                this._timeoutId = setTimeout(this._checkQueue.bind(this), this._interval);
            }
        });
    }

    _onPollIntervalChanged(newInterval) {
        this._interval = newInterval;
        if (this._isRequestRunning) {
            return;
        }

        if (this._timeoutId !== null) {
            clearTimeout(this._timeoutId);
            this._timeoutId = null;
        }

        if (newInterval === POLL_FOREGROUND_INTERVAL) {
            this._checkQueue();
            return;
        }

        this._timeoutId = setTimeout(this._checkQueue.bind(this), this._interval);
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
