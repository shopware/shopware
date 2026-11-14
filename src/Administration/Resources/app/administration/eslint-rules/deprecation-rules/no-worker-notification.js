/**
 * @sw-package framework
 *
 * Upgrade codemod `worker-notification`: 6.8 removes the increment-based message queue notifications. The rule marks
 * `Shopware.WorkerNotification.*` (also through `const { WorkerNotification } = Shopware`),
 * `getContainer('factory').workerNotification`, imports of the listener and the factory, `enableQueueStatsWorker` and
 * the route `/_info/queue.json`. The texts are in `no-worker-notification.data.js`.
 */

const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');
const { propertyName } = require('./upgrade-helpers/member-usage');
const { isFactoryContainer } = require('./upgrade-helpers/global-calls');
const data = require('./no-worker-notification.data');

module.exports = createTodoRule({
    ...data,
    visitors: (context, api) => ({
        MemberExpression(node) {
            const name = propertyName(node);

            if (name === data.containerFactory && isFactoryContainer(node.object)) {
                api.todo(node, data.containerMessage);
            } else if (name === data.configKey) {
                api.todo(node, data.configMessage);
            }
        },
        Property(node) {
            if (propertyName(node) !== data.configKey) {
                return;
            }

            // `const { enableQueueStatsWorker } = adminWorker` and `{ enableQueueStatsWorker: true }`
            api.todo(node, data.configMessage);
        },
    }),
});
