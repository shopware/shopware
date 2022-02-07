/**
 * @module core/worker/admin-worker
 */

import LoginService from 'src/core/service/login.service';
import ScheduledTaskService from 'src/core/service/api/scheduled-task.api.service';
import MessageQueueService from 'src/core/service/api/message-queue.api.service';
import Axios from 'axios';

// eslint-disable-next-line no-restricted-globals
self.onmessage = onMessage;

let isRunning = false;
let loginService;
let scheduledTaskService;
let messageQueueService;

function onMessage({ data: { context, bearerAuth, host, transports, type } }) {
    if (type === 'logout') {
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

    // only start listener once
    if (isRunning) {
        return;
    }
    isRunning = true;

    scheduledTaskService.getMinRunInterval().then((response) => {
        if (response.minRunInterval > 0) {
            const timeout = response.minRunInterval * 1000;
            runTasks(timeout);
        }
    });

    transports.forEach((receiver) => {
        consumeMessages(receiver);
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

    messageQueueService.consume(receiver)
        .then((response) => {
            // no message handled, set timeout to 20 seconds to send next consume call.
            // if a message handled, directly send next consume call.
            const timeout = response.handledMessages === 0 ? 20000 : 0;

            _setTimeout(() => {
                consumeMessages(receiver);
            }, timeout);
        })
        .catch((error) => {
            _setTimeout(() => {
                consumeMessages(receiver);
            }, 10000);

            return error;
        });
}

export default {
    onMessage,
    runTasks,
    consumeMessages,
};
