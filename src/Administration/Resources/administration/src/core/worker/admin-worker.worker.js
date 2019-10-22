/**
 * @module core/worker/admin-worker
 */

import LoginService from 'src/core/service/login.service';
import ScheduledTaskService from 'src/core/service/api/scheduled-task.api.service';
import MessageQueueService from 'src/core/service/api/message-queue.api.service';
import Axios from 'axios';

// eslint-disable-next-line
self.onmessage = ({ data: { context, bearerAuth, host, transports } }) => {
    const baseURL = process.env.NODE_ENV !== 'production' ? `${host}${context.apiResourcePath}` : context.apiResourcePath;
    const client = Axios.create({
        baseURL: baseURL
    });

    const loginService = LoginService(client, context, bearerAuth);
    const scheduledTaskService = new ScheduledTaskService(client, loginService);
    const messageQueueService = new MessageQueueService(client, loginService);

    scheduledTaskService.getMinRunInterval().then((response) => {
        if (response.minRunInterval > 0) {
            const timeout = response.minRunInterval * 1000;
            runTasks(scheduledTaskService, timeout);
        }
    });

    transports.forEach((receiver) => {
        consumeMessages(messageQueueService, receiver);
    });
};

function runTasks(scheduledTaskService, timeout) {
    scheduledTaskService.runTasks().catch((error) => {
        const { response: { status } } = error;

        if (status === 401) {
            postMessage('expiredToken');
        }
    });

    setTimeout(() => {
        runTasks(scheduledTaskService, timeout);
    }, timeout);
}

function consumeMessages(messageQueueService, receiver) {
    messageQueueService.consume(receiver)
        .then(() => {
            consumeMessages(messageQueueService, receiver);
        })
        .catch((error) => {
            const { response: { status } } = error;
            console.log('error', error);

            if (status === 401) {
                postMessage('expiredToken');
            }
        });
}
