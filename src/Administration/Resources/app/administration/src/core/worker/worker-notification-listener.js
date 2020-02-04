import Axios from 'axios';
import ApiService from 'src/core/service/api.service';

const { Application, WorkerNotification } = Shopware;

export const POLL_BACKGROUND_INTERVAL = 30000;
export const POLL_FOREGROUND_INTERVAL = 5000;

class WorkerNotificationListener {
    constructor(loginService, context) {
        this._messageQueueStatsService = WorkerNotificationListener.getMessageQueueStatsService(loginService, context);
        this._isRunning = false;
        this._isRequestRunning = false;
        this._interval = POLL_BACKGROUND_INTERVAL;
        this._isIntervalWatcherSetup = false;
        this._timeoutId = null;
        this._applicationRoot = null;
        this._middlewareHelper = null;
    }

    start() {
        this._isRunning = true;
        this._middlewareHelper = WorkerNotification.initialize();
        this._timeoutId = setTimeout(this._checkQueue.bind(this), this._interval);
        this.setupIntervalWatcher();
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

        Shopware.State.watch((state) => {
            return state.notification.workerProcessPollInterval;
        }, this._onPollIntervalChanged.bind(this));

        this._isIntervalWatcherSetup = true;
    }

    static getMessageQueueStatsService(loginService, context) {
        const baseURL = process.env.NODE_ENV !== 'production' ?
            `${window.location.origin}${context.apiResourcePath}` :
            context.apiResourcePath;

        const client = Axios.create({
            baseURL: baseURL
        });

        return new ApiService(client, loginService, 'message-queue-stats');
    }

    _checkQueue() {
        this._isRequestRunning = true;
        this._messageQueueStatsService.getList({}).then((res) => {
            this._isRequestRunning = false;
            this._timeoutId = null;

            // Notify the worker notification middleware
            this.runNotificationMiddleware(res);

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

    runNotificationMiddleware(response) {
        const appRoot = this._getApplicationRootReference();
        const queue = response.data;

        const middlewareParams = {
            $root: appRoot,
            notification: {
                create: (notification) => {
                    return appRoot.$store.dispatch(
                        'notification/createNotification',
                        notification
                    );
                },
                update: (notification) => {
                    return appRoot.$store.dispatch(
                        'notification/updateNotification',
                        notification
                    );
                }
            },
            response,
            queue
        };

        this._middlewareHelper.go(middlewareParams);
    }

    _getApplicationRootReference() {
        if (!this._applicationRoot) {
            this._applicationRoot = Application.getApplicationRoot();
        }

        return this._applicationRoot;
    }
}

export default WorkerNotificationListener;
