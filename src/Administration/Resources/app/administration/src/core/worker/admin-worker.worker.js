/**
 * @package admin
 *
 * @module core/worker/admin-worker
 */

import LoginService from 'src/core/service/login.service';
import ScheduledTaskService from 'src/core/service/api/scheduled-task.api.service';
import MessageQueueService from 'src/core/service/api/message-queue.api.service';
import Axios from 'axios';

// eslint-disable-next-line no-restricted-globals
self.onmessage = onMessage;

const { CancelToken } = Axios;
let isRunning = false;
let loginService;
let scheduledTaskService;
let messageQueueService;
let cancelTokenSource = CancelToken.source();
let consumeTimeoutIds = {};

function onMessage({ data: { context, bearerAuth, host, transports, type } }) {
    // This if statement is so ugly, because we cannot use ES6 Syntax in web workers
    if (type === 'logout' ||
        !(typeof context === 'object' &&
            context.hasOwnProperty('apiResourcePath') &&
            context.apiResourcePath
        )
    ) {
        isRunning = false;
        return;
    }

    const baseURL = process.env.NODE_ENV !== 'production' ? `${host}${context.apiResourcePath}` : context.apiResourcePath;
    const client = Axios.create({
        baseURL: baseURL,
        timeout: 1000 * 60 * 5,
    });

    loginService = new LoginService(client, context, bearerAuth);
    scheduledTaskService = new ScheduledTaskService(client, loginService);
    messageQueueService = new MessageQueueService(client, loginService);

    if (type === 'consumeReset') {
        cancelConsumeMessages();
    }

    // only start listener once
    if (isRunning) {
        return;
    }
    isRunning = true;

    transports.forEach((receiver) => {
        consumeMessages(receiver);
    });

    if (type === 'consumeReset') {
        return;
    }

    scheduledTaskService.getMinRunInterval().then((response) => {
        if (response.minRunInterval > 0) {
            const timeout = response.minRunInterval * 1000;
            runTasks(timeout);
        }
    });
}

function runTasks(timeout) {
    if (!isRunning) {
        return;
    }

    scheduledTaskService.runTasks().catch((error) => {
        const { response: { status } } = error;

        if (status === 401) {
            postMessage('expiredToken');
        }
    });

    setTimeout(() => {
        runTasks(timeout);
    }, timeout);
}

function consumeMessages(receiver, _setTimeout = setTimeout) {
    if (!isRunning) {
        return;
    }

    messageQueueService.consume(receiver, cancelTokenSource.token)
        .then((response) => {
            // no message handled, set timeout to 20 seconds to send next consume call.
            // if a message handled, directly send next consume call.
            const timeout = response.handledMessages === 0 ? 20000 : 0;

            consumeTimeoutIds[receiver] = _setTimeout(() => {
                consumeMessages(receiver);
            }, timeout);
        })
        .catch((error) => {
            if (Axios.isCancel(error)) {
                return error;
            }

            consumeTimeoutIds[receiver] = _setTimeout(() => {
                consumeMessages(receiver);
            }, 10000);

            return error;
        });
}

function cancelConsumeMessages() {
    cancelTokenSource.cancel();

    Object.values(consumeTimeoutIds).forEach((id) => {
        clearTimeout(id);
    });

    cancelTokenSource = CancelToken.source();
    consumeTimeoutIds = {};
    isRunning = false;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    onMessage,
    runTasks,
    consumeMessages,
    cancelConsumeMessages,
};
