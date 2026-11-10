/**
 * @sw-package framework
 */

import { apiUsageMigration, importSource, memberUsage, upgradeGuide } from '../helpers';

const MESSAGE =
    'Increment-based message queue notifications are removed in 6.8 without replacement. Remove this WorkerNotification middleware.';

export default apiUsageMigration({
    id: 'api.worker-notification',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'Shopware.WorkerNotification, its factory and the WorkerNotificationListener are removed. Registered middlewares no longer run.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removal-of-increment-based-message-queue-notifications'),
    ],
    usage: [
        memberUsage({ object: 'Shopware', properties: ['WorkerNotification'], message: MESSAGE }),
        memberUsage({ object: '*.adminWorker', properties: ['enableQueueStatsWorker'], message: MESSAGE }),
        importSource({
            sources: [
                'src/core/factory/worker-notification.factory',
                'src/core/worker/worker-notification-listener',
            ],
            message: MESSAGE,
        }),
    ],
});
