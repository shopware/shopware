/**
 * @sw-package framework
 *
 * Upgrade codemod `worker-notification`: 6.8 removes the increment-based message queue notifications. The rule marks
 * `Shopware.WorkerNotification.*` (also through `const { WorkerNotification } = Shopware`),
 * `getContainer('factory').workerNotification`, imports of the listener and the factory, `enableQueueStatsWorker` and
 * the route `/_info/queue.json`. The texts are in `no-worker-notification.data.js`.
 */

const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');
const { stringValue } = require('./upgrade-helpers/component-tags');
const data = require('./no-worker-notification.data');

function keyName(node) {
    if (node.computed) {
        return stringValue(node.type === 'MemberExpression' ? node.property : node.key);
    }

    const key = node.type === 'MemberExpression' ? node.property : node.key;

    return key.type === 'Identifier' ? key.name : stringValue(key);
}

/** `Shopware.Application.getContainer('factory')` and other `<x>.getContainer('factory')` calls. */
function isFactoryContainer(node) {
    return (
        node.type === 'CallExpression' &&
        node.callee.type === 'MemberExpression' &&
        keyName(node.callee) === 'getContainer' &&
        stringValue(node.arguments[0]) === 'factory'
    );
}

module.exports = createTodoRule({
    ...data,
    visitors: (context, api) => ({
        MemberExpression(node) {
            const name = keyName(node);

            if (name === data.containerFactory && isFactoryContainer(node.object)) {
                api.todo(node, data.containerMessage);
            } else if (name === data.configKey) {
                api.todo(node, data.configMessage);
            }
        },
        Property(node) {
            if (keyName(node) !== data.configKey) {
                return;
            }

            // `const { enableQueueStatsWorker } = adminWorker` and `{ enableQueueStatsWorker: true }`
            api.todo(node, data.configMessage);
        },
    }),
});
