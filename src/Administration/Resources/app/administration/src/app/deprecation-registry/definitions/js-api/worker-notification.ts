/**
 * @sw-package framework
 */

import { importSource, jsApiMigration, memberUsage, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.worker-notification',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'Increment-based message queue notifications are removed without replacement. Remove WorkerNotification middlewares.',
    references: [upgradeGuide('UPGRADE-6.8.md#removal-of-increment-based-message-queue-notifications')],
    usage: [
        memberUsage({ from: 'Shopware.WorkerNotification' }),
        memberUsage({ from: '*.workerNotification' }),
        memberUsage({ from: '*.enableQueueStatsWorker' }),
        importSource({ from: 'src/core/factory/worker-notification.factory' }),
        importSource({ from: 'src/core/worker/worker-notification-listener' }),
    ],
});
