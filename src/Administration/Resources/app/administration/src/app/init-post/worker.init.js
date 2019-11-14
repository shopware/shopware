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
    let notificationId = null;
    factory.register('generateThumbnailsMessage', {
        name: 'Shopware\\Core\\Content\\Media\\Message\\GenerateThumbnailsMessage',
        fn: function middleware(next, { entry, $root, notification }) {
            // Create notification config object
            const config = {
                title: $root.$tc('global.notification-center.worker-listener.thumbnailGeneration.title'),
                message: $root.$tc(
                    'global.notification-center.worker-listener.thumbnailGeneration.message',
                    entry.size
                ),
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
                });
                next();
            }

            // Update existing notification
            if (notificationId !== null) {
                config.uuid = notificationId;

                if (entry.size === 0) {
                    config.title = $root.$tc('global.default.success');
                    config.message = $root.$t(
                        'global.notification-center.worker-listener.thumbnailGeneration.messageSuccess'
                    );
                    config.isLoading = false;
                }
                notification.update(config);
            }

            // do your stuff and call next then
            next();
        }
    });

    return true;
}
