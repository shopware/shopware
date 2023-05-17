import AdminWorker from 'src/core/worker/admin-worker.worker';
import WorkerNotificationListener from 'src/core/worker/worker-notification-listener';
import AdminNotificationWorker from 'src/core/worker/admin-notification-worker';
import getRefreshTokenHelper from 'src/core/helper/refresh-token.helper';
import type { ApiContext } from '@shopware-ag/admin-extension-sdk/es/data/_internals/EntityCollection';
import type Vue from 'vue';
import type { LoginService } from '../../core/service/login.service';
import type { ContextState } from '../state/context.store';
import type {
    NotificationConfig,
    NotificationService,
    NotificationWorkerOptions,
} from '../../core/factory/worker-notification.factory';
import type WorkerNotificationFactory from '../../core/factory/worker-notification.factory';

let enabled = false;
let enabledNotification = false;

/**
 * @package admin
 *
 * Starts the worker
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeWorker() {
    const loginService = Shopware.Service('loginService');
    const context = Shopware.Context.app;
    const workerNotificationFactory = Shopware.Application.getContainer('factory').workerNotification;
    const configService = Shopware.Service('configService');

    registerThumbnailMiddleware(workerNotificationFactory);

    function getConfig() {
        return configService.getConfig().then((response) => {
            Object.entries(response as { [key: string]: unknown}).forEach(([key, value]) => {
                Shopware.State.commit('context/addAppConfigValue', { key, value });
            });

            // Enable worker notification listener regardless of the config
            enableWorkerNotificationListener(
                loginService,
                Shopware.Context.api,
            );

            // Enable worker notification listener regardless of the config
            if (!enabledNotification) {
                enableNotificationWorker(loginService);
            }

            if (context.config.adminWorker?.enableAdminWorker && !enabled) {
                enableAdminWorker(loginService, Shopware.Context.api, context.config.adminWorker);
            }
        });
    }

    if (loginService.isLoggedIn()) {
        return getConfig().catch();
    }

    return loginService.addOnLoginListener(getConfig);
}

function enableAdminWorker(
    loginService: LoginService,
    context: ApiContext,
    config: ContextState['app']['config']['adminWorker'],
) {
    const getMessage = () => {
        return {
            context: {
                languageId: context.languageId,
                apiResourcePath: context.apiResourcePath,
            },
            bearerAuth: loginService.getBearerAuthentication(),
            host: window.location.origin,
            transports: config?.transports || [],
        };
    };

    if (loginService.isLoggedIn()) {
        getWorker().postMessage(getMessage());
    }

    loginService.addOnTokenChangedListener((auth) => {
        getWorker().postMessage({ ...getMessage(), ...{ bearerAuth: auth } });
    });

    loginService.addOnLogoutListener(() => {
        getWorker().postMessage({ type: 'logout' });
    });

    const importExportService = Shopware.Service('importExport');

    importExportService.addOnProgressStartedListener(() => {
        getWorker().postMessage({ ...getMessage(), ...{ type: 'consumeReset' } });
    });

    enabled = true;
}

// singleton instance of worker
let worker: Worker;

/* istanbul ignore next */
function getWorker() : Worker {
    if (worker) {
        return worker;
    }

    // The webpack worker plugin generates a valid worker file therefore we can use it here
    // @ts-expect-error
    worker = new AdminWorker() as Worker;

    worker.onmessage = () => {
        const tokenHandler = getRefreshTokenHelper();

        if (!tokenHandler.isRefreshing) {
            void tokenHandler.fireRefreshTokenRequest();
        }
    };

    return worker;
}

function enableWorkerNotificationListener(loginService: LoginService, context: ContextState['api']) {
    let workerNotificationListener = new WorkerNotificationListener(context);

    if (loginService.isLoggedIn()) {
        workerNotificationListener.start();
    }

    loginService.addOnTokenChangedListener(() => {
        workerNotificationListener.terminate();
        workerNotificationListener = new WorkerNotificationListener(context);
        workerNotificationListener.start();
    });

    loginService.addOnLogoutListener(() => {
        workerNotificationListener.terminate();
        workerNotificationListener = new WorkerNotificationListener(context);
    });
}

function enableNotificationWorker(loginService: LoginService) {
    let notificationWorker = new AdminNotificationWorker();

    if (loginService.isLoggedIn()) {
        notificationWorker.start();
    }

    loginService.addOnTokenChangedListener(() => {
        notificationWorker.terminate();
        notificationWorker = new AdminNotificationWorker();
        notificationWorker.start();
    });

    loginService.addOnLogoutListener(() => {
        notificationWorker.terminate();
        notificationWorker = new AdminNotificationWorker();
    });

    enabledNotification = true;
}

function registerThumbnailMiddleware(factory: typeof WorkerNotificationFactory) {
    const ids = {};
    factory.register('DalIndexingMessage', {
        name: 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Indexing\\MessageQueue\\IndexerMessage',
        fn: function middleware(next, { entry, $root, notification }) {
            messageQueueNotification('dalIndexing', ids, next, entry, $root, notification, {
                title: 'global.default.success',
                message: 'global.notification-center.worker-listener.dalIndexing.message',
                success: 'global.notification-center.worker-listener.dalIndexing.messageSuccess',
                foregroundSuccessMessage: 'sw-settings-cache.notifications.index.success',
            });
        },
    });

    factory.register('WarmupIndexingMessage', {
        name: 'Shopware\\Storefront\\Framework\\Cache\\CacheWarmer\\WarmUpMessage',
        fn: function middleware(next, { entry, $root, notification }) {
            messageQueueNotification('warmupMessage', ids, next, entry, $root, notification, {
                title: 'global.default.success',
                message: 'global.notification-center.worker-listener.warmupIndexing.message',
                success: 'global.notification-center.worker-listener.warmupIndexing.messageSuccess',
                foregroundSuccessMessage: 'sw-settings-cache.notifications.clearCacheAndWarmup.success',
            }, 10);
        },
    });

    factory.register('EsIndexingMessage', {
        name: 'Shopware\\Elasticsearch\\Framework\\Indexing\\IndexingMessage',
        fn: function middleware(next, { entry, $root, notification }) {
            messageQueueNotification('esIndexing', ids, next, entry, $root, notification, {
                title: 'global.default.success',
                message: 'global.notification-center.worker-listener.esIndexing.message',
                success: 'global.notification-center.worker-listener.esIndexing.messageSuccess',
            });
        },
    });

    factory.register('generateThumbnailsMessage', {
        name: 'Shopware\\Core\\Content\\Media\\Message\\GenerateThumbnailsMessage',
        fn: function middleware(next, { entry, $root, notification }) {
            messageQueueNotification('thumbnails', ids, next, entry, $root, notification, {
                title: 'global.notification-center.worker-listener.thumbnailGeneration.title',
                message: 'global.notification-center.worker-listener.thumbnailGeneration.message',
                success: 'global.notification-center.worker-listener.thumbnailGeneration.messageSuccess',
            });
        },
    });

    factory.register('PromotionIndexingMessage', {
        name: 'Shopware\\Core\\Checkout\\Promotion\\DataAbstractionLayer\\PromotionIndexingMessage',
        fn: function middleware(next, { entry, $root, notification }) {
            messageQueueNotification('promotion', ids, next, entry, $root, notification, {
                title: 'global.notification-center.worker-listener.promotion.title',
                message: 'global.notification-center.worker-listener.promotion.message',
                success: 'global.notification-center.worker-listener.promotion.messageSuccess',
            }, 50);
        },
    });

    factory.register('ProductStreamIndexingMessage', {
        name: 'Shopware\\Core\\Content\\ProductStream\\DataAbstractionLayer\\ProductStreamIndexingMessage',
        fn: function middleware(next, { entry, $root, notification }) {
            messageQueueNotification('productStream', ids, next, entry, $root, notification, {
                title: 'global.notification-center.worker-listener.productStream.title',
                message: 'global.notification-center.worker-listener.productStream.message',
                success: 'global.notification-center.worker-listener.productStream.messageSuccess',
            }, 50);
        },
    });

    factory.register('CategoryIndexingMessage', {
        name: 'Shopware\\Core\\Content\\Category\\DataAbstractionLayer\\CategoryIndexingMessage',
        fn: function middleware(next, { entry, $root, notification }) {
            messageQueueNotification('category', ids, next, entry, $root, notification, {
                title: 'global.notification-center.worker-listener.category.title',
                message: 'global.notification-center.worker-listener.category.message',
                success: 'global.notification-center.worker-listener.category.messageSuccess',
            }, 50);
        },
    });

    factory.register('MediaIndexingMessage', {
        name: 'Shopware\\Core\\Content\\Media\\DataAbstractionLayer\\MediaIndexingMessage',
        fn: function middleware(next, { entry, $root, notification }) {
            messageQueueNotification('media', ids, next, entry, $root, notification, {
                title: 'global.notification-center.worker-listener.media.title',
                message: 'global.notification-center.worker-listener.media.message',
                success: 'global.notification-center.worker-listener.media.messageSuccess',
            }, 50);
        },
    });

    factory.register('SalesChannelIndexingMessage', {
        name: 'Shopware\\Core\\System\\SalesChannel\\DataAbstractionLayer\\SalesChannelIndexingMessage',
        fn: function middleware(next, { entry, $root, notification }) {
            messageQueueNotification('salesChannel', ids, next, entry, $root, notification, {
                title: 'global.notification-center.worker-listener.salesChannel.title',
                message: 'global.notification-center.worker-listener.salesChannel.message',
                success: 'global.notification-center.worker-listener.salesChannel.messageSuccess',
            }, 50);
        },
    });

    factory.register('RuleIndexingMessage', {
        name: 'Shopware\\Core\\Content\\Rule\\DataAbstractionLayer\\RuleIndexingMessage',
        fn: function middleware(next, { entry, $root, notification }) {
            messageQueueNotification('rule', ids, next, entry, $root, notification, {
                title: 'global.notification-center.worker-listener.rule.title',
                message: 'global.notification-center.worker-listener.rule.message',
                success: 'global.notification-center.worker-listener.rule.messageSuccess',
            }, 50);
        },
    });

    factory.register('ProductIndexingMessage', {
        name: 'Shopware\\Core\\Content\\Product\\DataAbstractionLayer\\ProductIndexingMessage',
        fn: function middleware(next, { entry, $root, notification }) {
            messageQueueNotification('product', ids, next, entry, $root, notification, {
                title: 'global.notification-center.worker-listener.product.title',
                message: 'global.notification-center.worker-listener.product.message',
                success: 'global.notification-center.worker-listener.product.messageSuccess',
            }, 50);
        },
    });

    factory.register('ElasticSearchIndexingMessage', {
        name: 'Shopware\\Elasticsearch\\Framework\\Indexing\\ElasticsearchIndexingMessage',
        fn: function middleware(next, { entry, $root, notification }) {
            messageQueueNotification('esIndexing', ids, next, entry, $root, notification, {
                title: 'global.default.success',
                message: 'global.notification-center.worker-listener.esIndexing.message',
                success: 'global.notification-center.worker-listener.esIndexing.messageSuccess',
            }, 50);
        },
    });

    factory.register('ImportExportMessage', {
        name: 'Shopware\\Core\\Content\\ImportExport\\Message\\ImportExportMessage',
        fn: function middleware(next, { entry, $root, notification }) {
            messageQueueNotification('importExport', ids, next, entry, $root, notification, {
                title: 'global.notification-center.worker-listener.importExport.title',
                message: 'global.notification-center.worker-listener.importExport.message',
                success: 'global.notification-center.worker-listener.importExport.messageSuccess',
            });
        },
    });

    factory.register('FlowIndexingMessage', {
        name: 'Shopware\\Core\\Content\\Flow\\Indexing\\FlowIndexingMessage',
        fn: function middleware(next, { entry, $root, notification }) {
            messageQueueNotification('flow', ids, next, entry, $root, notification, {
                title: 'global.notification-center.worker-listener.flow.title',
                message: 'global.notification-center.worker-listener.flow.message',
                success: 'global.notification-center.worker-listener.flow.messageSuccess',
            });
        },
    });

    factory.register('NewsletterRecipientIndexingMessage', {
        name: 'Shopware\\Core\\Content\\Newsletter\\DataAbstractionLayer\\NewsletterRecipientIndexingMessage',
        fn: function middleware(next, { entry, $root, notification }) {
            messageQueueNotification('newsletterRecipient', ids, next, entry, $root, notification, {
                title: 'global.notification-center.worker-listener.newsletterRecipient.title',
                message: 'global.notification-center.worker-listener.newsletterRecipient.message',
                success: 'global.notification-center.worker-listener.newsletterRecipient.messageSuccess',
            });
        },
    });

    return true;
}

function messageQueueNotification(
    key: string,
    ids: { [key: string]: { notificationId: string, didSendForegroundMessage: boolean}},
    next: (name?: string, opts?: NotificationWorkerOptions) => unknown,
    entry: { size: number },
    $root: Vue,
    notification: NotificationService,
    messages: { title: string, message: string, success: string, foregroundSuccessMessage?: string },
    multiplier = 1,
) {
    let notificationId = null;
    let didSendForegroundMessage = false;

    if (ids.hasOwnProperty((key))) {
        notificationId = ids[key].notificationId;
        didSendForegroundMessage = ids[key].didSendForegroundMessage;
    }

    if (entry.size) {
        entry.size *= multiplier;
    }


    const config: NotificationConfig = {
        title: $root.$tc(messages.title),
        message: $root.$tc(messages.message, entry.size),
        variant: 'info',
        metadata: {
            size: entry.size,
        },
        growl: false,
        isLoading: true,
    };

    // Create new notification
    if (entry.size && notificationId === null) {
        void notification.create(config).then((uuid) => {
            notificationId = uuid;

            ids[key] = {
                notificationId,
                didSendForegroundMessage: false,
            };
        });
        next();
    }

    // Update existing notification
    if (notificationId !== null) {
        config.uuid = notificationId;

        if (entry.size === 0) {
            config.title = $root.$tc(messages.title);
            config.message = $root.$t(messages.success) as string;
            config.isLoading = false;

            if (messages.foregroundSuccessMessage && !didSendForegroundMessage) {
                const foreground = { ...config };
                foreground.message = $root.$t(messages.foregroundSuccessMessage) as string;
                delete foreground.uuid;
                delete foreground.isLoading;
                foreground.growl = true;
                foreground.variant = 'success';
                void notification.create(foreground);

                ids[key] = {
                    notificationId,
                    didSendForegroundMessage: true,
                };
            }

            delete ids[key];
        }
        void notification.update(config);
    }
    next();
}
