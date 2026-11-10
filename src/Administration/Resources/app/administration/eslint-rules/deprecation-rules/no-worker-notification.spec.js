/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-worker-notification');
const data = require('./no-worker-notification.data');

const GLOBAL = data.globals[0].message;
const LISTENER = data.imports[0].message;
const FACTORY = data.imports[1].message;
const ROUTE = data.literals[0].message;
const todo = (text) => `// TODO Codemod(6.8, worker-notification): ${text}`;

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-worker-notification', rule, {
    valid: [
        { name: 'ignores the message stats service', code: "Shopware.Service('messageStatsService').getStats();\nconst url = '/_info/message-stats.json';" },
        { name: 'ignores the notification store', code: "Shopware.Store.get('notification').workerProcessPollInterval;" },
        { name: 'ignores other container factories', code: "Shopware.Application.getContainer('factory').component;\nthis.workerNotification;" },
        { name: 'ignores other worker imports', code: "import AdminWorker from 'src/core/worker/admin-worker';" },
    ],
    invalid: [
        {
            name: 'marks Shopware.WorkerNotification, also destructured',
            code: [
                "Shopware.WorkerNotification.register('AcmeIndexingMessage', {",
                "    name: 'Acme\\\\Indexing\\\\Message',",
                '    fn: (next) => next(),',
                '});',
                'const { WorkerNotification } = Shopware;',
                "WorkerNotification.remove('DalIndexingMessage');",
            ].join('\n'),
            output: [
                todo(GLOBAL),
                "Shopware.WorkerNotification.register('AcmeIndexingMessage', {",
                "    name: 'Acme\\\\Indexing\\\\Message',",
                '    fn: (next) => next(),',
                '});',
                'const { WorkerNotification } = Shopware;',
                todo(GLOBAL),
                "WorkerNotification.remove('DalIndexingMessage');",
            ].join('\n'),
            errors: [{ message: GLOBAL }, { message: GLOBAL }],
        },
        {
            name: 'marks the container factory, the config flag and the route',
            code: [
                "const factory = Shopware.Application.getContainer('factory').workerNotification;",
                'if (Shopware.Context.app.config.adminWorker?.enableQueueStatsWorker) {}',
                'const { enableQueueStatsWorker } = adminWorker;',
                "this.httpClient.get('/_info/queue.json');",
            ].join('\n'),
            output: [
                todo(data.containerMessage),
                "const factory = Shopware.Application.getContainer('factory').workerNotification;",
                todo(data.configMessage),
                'if (Shopware.Context.app.config.adminWorker?.enableQueueStatsWorker) {}',
                todo(data.configMessage),
                'const { enableQueueStatsWorker } = adminWorker;',
                todo(ROUTE),
                "this.httpClient.get('/_info/queue.json');",
            ].join('\n'),
            errors: [{ message: data.containerMessage }, { message: data.configMessage }, { message: data.configMessage }, { message: ROUTE }],
        },
        {
            name: 'marks imports of the listener and the factory',
            code: [
                "import WorkerNotificationListener, { POLL_BACKGROUND_INTERVAL } from 'src/core/worker/worker-notification-listener';",
                "import type { NotificationWorkerOptions } from 'src/core/factory/worker-notification.factory';",
            ].join('\n'),
            output: [
                todo(LISTENER),
                "import WorkerNotificationListener, { POLL_BACKGROUND_INTERVAL } from 'src/core/worker/worker-notification-listener';",
                todo(FACTORY),
                "import type { NotificationWorkerOptions } from 'src/core/factory/worker-notification.factory';",
            ].join('\n'),
            errors: [{ message: LISTENER }, { message: FACTORY }],
            languageOptions: { parser: require('@typescript-eslint/parser') },
        },
    ],
});
