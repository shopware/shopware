/**
 * @package admin
 */

import AdminWorker from 'src/core/worker/admin-worker';
import Axios from 'axios';
import AxiosMockAdapter from 'axios-mock-adapter';

const axiosMock = new AxiosMockAdapter(Axios);

function getConsumeRequests(history) {
    return history.post.filter((r) => r.url === '/_action/message-queue/consume');
}

describe('core/worker/admin-worker.worker.js', () => {
    beforeEach(async () => {
        await AdminWorker.onMessage({ data: { type: 'logout' } });
        axiosMock.reset();
        axiosMock.onAny().reply(200);
        jest.useFakeTimers();
    });

    afterEach(async () => {
        await jest.runAllTimers();
        axiosMock.reset();
        jest.useRealTimers();
    });

    it('should call the consume call at the beginning', async () => {
        expect(getConsumeRequests(axiosMock.history)).toHaveLength(0);

        AdminWorker.onMessage({
            data: {
                context: {
                    languageId: 'language_id',
                    apiResourcePath: 'api_resource_path',
                },
                bearerAuth: 'bearer_auth',
                host: 'http://www.shopware.com',
                transports: ['default'],
            },
        });

        expect(getConsumeRequests(axiosMock.history)).toHaveLength(1);
    });

    it('should not resend the consume call on token refresh', async () => {
        expect(getConsumeRequests(axiosMock.history)).toHaveLength(0);

        AdminWorker.onMessage({
            data: {
                context: {
                    languageId: 'language_id',
                    apiResourcePath: 'api_resource_path',
                },
                bearerAuth: 'bearer_auth',
                host: 'http://www.shopware.com',
                transports: ['default'],
            },
        });

        expect(getConsumeRequests(axiosMock.history)).toHaveLength(1);

        AdminWorker.onMessage({
            data: {
                context: {
                    languageId: 'language_id',
                    apiResourcePath: 'api_resource_path',
                },
                bearerAuth: 'bearer_auth',
                host: 'http://www.shopware.com',
                transports: ['default'],
            },
        });

        expect(getConsumeRequests(axiosMock.history)).toHaveLength(1);
    });

    it('should restart the consume call after 20 seconds when handledMessages dont exist', async () => {
        axiosMock.reset();
        axiosMock.onAny().reply(() => {
            return [
                200,
                { handledMessages: 0 },
            ];
        });

        expect(getConsumeRequests(axiosMock.history)).toHaveLength(0);

        await AdminWorker.onMessage({
            data: {
                context: {
                    languageId: 'language_id',
                    apiResourcePath: 'api_resource_path',
                },
                bearerAuth: 'bearer_auth',
                host: 'http://www.shopware.com',
                transports: ['default'],
            },
        }); // start AdminWorker
        await jest.runAllTimers(); // start consumeMessages
        await jest.runAllTimers(); // consume firstMessage

        expect(getConsumeRequests(axiosMock.history)).toHaveLength(1);

        // should retry after 20 seconds
        await jest.advanceTimersByTime(19999);
        expect(getConsumeRequests(axiosMock.history)).toHaveLength(1);
        await jest.advanceTimersByTime(1);
        expect(getConsumeRequests(axiosMock.history)).toHaveLength(2);
    });

    it('should restart the consume call directly when handledMessages exist', async () => {
        axiosMock.reset();
        axiosMock.onAny().reply(() => {
            return [
                200,
                { handledMessages: 50 },
            ];
        });

        expect(getConsumeRequests(axiosMock.history)).toHaveLength(0);

        await AdminWorker.onMessage({
            data: {
                context: {
                    languageId: 'language_id',
                    apiResourcePath: 'api_resource_path',
                },
                bearerAuth: 'bearer_auth',
                host: 'http://www.shopware.com',
                transports: ['default'],
            },
        }); // start AdminWorker
        await jest.runAllTimers(); // start consumeMessages
        await jest.runAllTimers(); // consume firstMessage

        expect(getConsumeRequests(axiosMock.history)).toHaveLength(1);

        // should retry after 20 seconds
        await jest.advanceTimersByTime(0);
        expect(getConsumeRequests(axiosMock.history)).toHaveLength(2);
    });

    it('should reset timeout to send request before 20 seconds (no messages)', async () => {
        axiosMock.reset();
        axiosMock.onAny().reply(() => {
            return [
                200,
                { handledMessages: 0 },
            ];
        });

        expect(getConsumeRequests(axiosMock.history)).toHaveLength(0);

        const message = {
            context: {
                languageId: 'language_id',
                apiResourcePath: 'api_resource_path',
            },
            bearerAuth: 'bearer_auth',
            host: 'http://www.shopware.com',
            transports: ['default'],
        };

        await AdminWorker.onMessage({ data: message }); // start AdminWorker
        await jest.runAllTimers(); // start consumeMessages
        await jest.runAllTimers(); // consume firstMessage

        expect(getConsumeRequests(axiosMock.history)).toHaveLength(1);

        await jest.advanceTimersByTime(500);

        await AdminWorker.onMessage({
            data: { ...message, ...{ type: 'consumeReset' } },
        }); // reset consume cycle
        await jest.runAllTimers(); // start consumeMessages
        await jest.runAllTimers(); // consume firstMessage

        expect(getConsumeRequests(axiosMock.history)).toHaveLength(2);

        // there should not have been a request since timeout should have been cleared earlier
        await jest.advanceTimersByTime(19500);
        expect(getConsumeRequests(axiosMock.history)).toHaveLength(2);

        // should be the first request by the new timeout after the earlier one has been reset
        await jest.advanceTimersByTime(500);
        expect(getConsumeRequests(axiosMock.history)).toHaveLength(3);
    });

    it('should cancel current consume request', async () => {
        axiosMock.reset();
        axiosMock.onAny().reply(() => {
            return [
                200,
                { handledMessages: 0 },
            ];
        });

        expect(getConsumeRequests(axiosMock.history)).toHaveLength(0);

        const isCanceled = (index) => {
            const cancelToken = getConsumeRequests(axiosMock.history)[index].cancelToken;

            return !!cancelToken.reason;
        };

        const message = {
            context: {
                languageId: 'language_id',
                apiResourcePath: 'api_resource_path',
            },
            bearerAuth: 'bearer_auth',
            host: 'http://www.shopware.com',
            transports: ['default'],
        };

        await AdminWorker.onMessage({ data: message }); // start AdminWorker

        expect(getConsumeRequests(axiosMock.history)).toHaveLength(1);
        expect(isCanceled(0)).toBeFalsy();

        await AdminWorker.onMessage({
            data: { ...message, ...{ type: 'consumeReset' } },
        }); // reset consume cycle

        expect(getConsumeRequests(axiosMock.history)).toHaveLength(2);
        expect(isCanceled(0)).toBeTruthy();
        expect(isCanceled(1)).toBeFalsy();
    });

    it('should set the onMessage method to the first port on connect', async () => {
        const mockEvent = {
            ports: [
                { postMessage: jest.fn() },
            ],
        };

        expect(mockEvent.ports[0].onmessage).toBeUndefined();

        AdminWorker.onconnect(mockEvent);

        expect(mockEvent.ports[0].onmessage).toBe(AdminWorker.onMessage);
    });
});
