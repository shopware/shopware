/**
 * @sw-package framework
 *
 * Texts of the `worker-notification` codemod. 6.8 removes the increment-based message queue notifications without
 * replacement: `Shopware.WorkerNotification`, the `workerNotification` factory of the container, the
 * `WorkerNotificationListener` with its `POLL_*` constants, `adminWorker.enableQueueStatsWorker` and the
 * `/api/_info/queue.json` route. Taken from the `@deprecated tag:v6.8.0` markers in
 * `src/core/factory/worker-notification.factory.ts`, `src/core/worker/worker-notification-listener.js` and
 * `src/app/init-post/worker.init.ts`.
 */

const STATS = "for queue statistics use Shopware.Service('messageStatsService').getStats()";

module.exports = {
    id: 'worker-notification',
    description: 'Marks the increment-based message queue notification APIs, which 6.8 removes',
    globals: [
        {
            path: 'Shopware.WorkerNotification',
            message: `Shopware.WorkerNotification is removed without replacement. Remove this middleware; ${STATS}.`,
        },
    ],
    containerFactory: 'workerNotification',
    containerMessage:
        "getContainer('factory').workerNotification is removed with Shopware.WorkerNotification without replacement. " +
        `Remove this code; ${STATS}.`,
    configKey: 'enableQueueStatsWorker',
    configMessage:
        'adminWorker.enableQueueStatsWorker is removed with the increment-based queue notifications. ' +
        `Remove this check; ${STATS}.`,
    imports: [
        {
            source: /^src\/core\/worker\/worker-notification-listener(\.js)?$/,
            message:
                'WorkerNotificationListener, POLL_BACKGROUND_INTERVAL and POLL_FOREGROUND_INTERVAL are removed without ' +
                'replacement. Remove this import.',
        },
        {
            source: /^src\/core\/factory\/worker-notification\.factory(\.ts)?$/,
            message:
                'The worker notification factory and its types NotificationService and NotificationWorkerOptions are ' +
                'removed with Shopware.WorkerNotification. Remove this import.',
        },
    ],
    literals: [
        {
            value: /\/_info\/queue\.json/,
            message:
                "/api/_info/queue.json is removed. Use /api/_info/message-stats.json through Shopware.Service('messageStatsService').getStats().",
        },
    ],
};
