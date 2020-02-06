import AdminWorker from 'src/core/worker/admin-worker.worker';
import WorkerNotificationListener from 'src/core/worker/worker-notification-listener';

let enabled = false;

/**
 * Starts the worker
 */
export default function initializeWorker() {
    const loginService = Shopware.Service('loginService');
    const context = Shopware.Context.app;
    const workerNotificationFactory = this.getContainer('factory').workerNotification;
    const configService = Shopware.Service('configService');

    registerThumbnailMiddleware(workerNotificationFactory);

    function getConfig() {
        return configService.getConfig().then((response) => {
            Object.entries(response).forEach(([key, value]) => {
                Shopware.State.commit('context/addAppConfigValue', { key, value });
            });

            // Enable worker notification listener regardless of the config
            enableWorkerNotificationListener(
                loginService,
                Shopware.Context.api
            );

            if (context.config.adminWorker.enableAdminWorker && !enabled) {
                enableAdminWorker(loginService, Shopware.Context.api, context.config.adminWorker);
            }
        });
    }

    if (loginService.isLoggedIn()) {
        return getConfig().catch();
    }

    return loginService.addOnLoginListener(getConfig);
}

function enableAdminWorker(loginService, context, config) {
    let worker = getWorker(loginService);

    if (loginService.isLoggedIn()) {
        worker.postMessage({
            context: {
                languageId: context.languageId,
                apiResourcePath: context.apiResourcePath
            },
            bearerAuth: loginService.getBearerAuthentication(),
            host: window.location.origin,
            transports: config.transports
        });
    }

    loginService.addOnTokenChangedListener((auth) => {
        worker.terminate();
        worker = getWorker(loginService);
        worker.postMessage({
            context: {
                languageId: context.languageId,
                apiResourcePath: context.apiResourcePath
            },
            bearerAuth: auth,
            host: window.location.origin,
            transports: config.transports
        });
    });

    loginService.addOnLogoutListener(() => {
        worker.terminate();
        worker = getWorker(loginService);
    });

    enabled = true;
}

function getWorker(loginService) {
    const worker = new AdminWorker();

    worker.onmessage = () => {
        loginService.refreshToken();
    };

    return worker;
}

function enableWorkerNotificationListener(loginService, context) {
    let workerNotificationListener = new WorkerNotificationListener(loginService, context);

    if (loginService.isLoggedIn()) {
        workerNotificationListener.start();
    }

    loginService.addOnTokenChangedListener(() => {
        workerNotificationListener.terminate();
        workerNotificationListener = new WorkerNotificationListener(loginService, context);
        workerNotificationListener.start();
    });

    loginService.addOnLogoutListener(() => {
        workerNotificationListener.terminate();
        workerNotificationListener = new WorkerNotificationListener(loginService, context);
    });
}

function registerThumbnailMiddleware(factory) {
    const ids = {};
    factory.register('DalIndexingMessage', {
        name: 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Indexing\\MessageQueue\\IndexerMessage',
        fn: function middleware(next, { entry, $root, notification }) {
            messageQueueNotification('dalIndexing', ids, next, entry, $root, notification, {
                title: 'global.default.success',
                message: 'global.notification-center.worker-listener.dalIndexing.message',
                success: 'global.notification-center.worker-listener.dalIndexing.messageSuccess',
                foregroundSuccessMessage: 'sw-settings-cache.notifications.index.success'
            });
        }
    });

    factory.register('WarmupIndexingMessage', {
        name: 'Shopware\\Storefront\\Framework\\Cache\\CacheWarmer\\WarmUpMessage',
        fn: function middleware(next, { entry, $root, notification }) {
            messageQueueNotification('warmupMessage', ids, next, entry, $root, notification, {
                title: 'global.default.success',
                message: 'global.notification-center.worker-listener.warmupIndexing.message',
                success: 'global.notification-center.worker-listener.warmupIndexing.messageSuccess',
                foregroundSuccessMessage: 'sw-settings-cache.notifications.clearCacheAndWarmup.success'
            });
        }
    });

    factory.register('EsIndexingMessage', {
        name: 'Shopware\\Elasticsearch\\Framework\\Indexing\\IndexingMessage',
        fn: function middleware(next, { entry, $root, notification }) {
            messageQueueNotification('esIndexing', ids, next, entry, $root, notification, {
                title: 'global.default.success',
                message: 'global.notification-center.worker-listener.esIndexing.message',
                success: 'global.notification-center.worker-listener.esIndexing.messageSuccess'
            });
        }
    });

    factory.register('generateThumbnailsMessage', {
        name: 'Shopware\\Core\\Content\\Media\\Message\\GenerateThumbnailsMessage',
        fn: function middleware(next, { entry, $root, notification }) {
            messageQueueNotification('thumbnails', ids, next, entry, $root, notification, {
                title: 'global.notification-center.worker-listener.thumbnailGeneration.title',
                message: 'global.notification-center.worker-listener.thumbnailGeneration.message',
                success: 'global.notification-center.worker-listener.thumbnailGeneration.messageSuccess'
            });
        }
    });

    return true;
}

function messageQueueNotification(key, ids, next, entry, $root, notification, messages) {
    let notificationId = null;
    let didSendForegroundMessage = false;

    if (ids.hasOwnProperty((key))) {
        notificationId = ids[key].notificationId;
        didSendForegroundMessage = ids[key].didSendForegroundMessage;
    }

    if (key === 'warmupMessage') {
        entry.size *= 10;
    }


    const config = {
        title: $root.$tc(messages.title),
        message: $root.$tc(messages.message, entry.size),
        variant: 'info',
        metadata: {
            size: entry.size
        },
        growl: false,
        isLoading: true
    };

    // Create new notification
    if (entry.size && notificationId === null) {
        notification.create(config).then((uuid) => {
            notificationId = uuid;

            ids[key] = {
                notificationId,
                didSendForegroundMessage: false
            };
        });
        next();
    }

    // Update existing notification
    if (notificationId !== null) {
        config.uuid = notificationId;

        if (entry.size === 0) {
            config.title = $root.$tc(messages.title);
            config.message = $root.$t(messages.success);
            config.isLoading = false;

            if (messages.foregroundSuccessMessage && !didSendForegroundMessage) {
                const foreground = Object.assign({}, config);
                foreground.message = $root.$t(messages.foregroundSuccessMessage);
                delete foreground.uuid;
                delete foreground.isLoading;
                foreground.growl = true;
                foreground.variant = 'success';
                notification.create(foreground);

                ids[key] = {
                    notificationId,
                    didSendForegroundMessage: true
                };
            }

            delete ids[key];
        }
        notification.update(config);
    }
    next();
}
