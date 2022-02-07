import AdminWorker from 'src/core/worker/admin-worker.worker';
import Axios from 'axios';
import AxiosMockAdapter from 'axios-mock-adapter';

jest.useFakeTimers();

const axiosMock = new AxiosMockAdapter(Axios);

function getConsumeRequests(history) {
    return history.post.filter(r => r.url === '/_action/message-queue/consume');
}

describe('core/worker/admin-worker.worker.js', () => {
    beforeEach(async () => {
        await AdminWorker.onMessage({ data: { type: 'logout' } });
        await jest.runAllTimers();
        axiosMock.reset();
        axiosMock.onAny().reply(200);
    });

    it('should call the consume call at the beginning', async () => {
        expect(getConsumeRequests(axiosMock.history)).toHaveLength(0);

        AdminWorker.onMessage({ data: {
            context: {
                languageId: 'language_id',
                apiResourcePath: 'api_resource_path',
            },
            bearerAuth: 'bearer_auth',
            host: 'http://www.shopware.com',
            transports: ['default'],
        } });

        expect(getConsumeRequests(axiosMock.history)).toHaveLength(1);
    });

    it('should not resend the consume call on token refresh', async () => {
        expect(getConsumeRequests(axiosMock.history)).toHaveLength(0);

        AdminWorker.onMessage({ data: {
            context: {
                languageId: 'language_id',
                apiResourcePath: 'api_resource_path',
            },
            bearerAuth: 'bearer_auth',
            host: 'http://www.shopware.com',
            transports: ['default'],
        } });

        expect(getConsumeRequests(axiosMock.history)).toHaveLength(1);

        AdminWorker.onMessage({ data: {
            context: {
                languageId: 'language_id',
                apiResourcePath: 'api_resource_path',
            },
            bearerAuth: 'bearer_auth',
            host: 'http://www.shopware.com',
            transports: ['default'],
        } });

        expect(getConsumeRequests(axiosMock.history)).toHaveLength(1);
    });

    it('should restart the consume call after 20 seconds when handledMessages dont exist', async () => {
        axiosMock.reset();
        axiosMock.onAny().reply(() => {
            return [200, { handledMessages: 0 }];
        });

        expect(getConsumeRequests(axiosMock.history)).toHaveLength(0);

        await AdminWorker.onMessage({ data: {
            context: {
                languageId: 'language_id',
                apiResourcePath: 'api_resource_path',
            },
            bearerAuth: 'bearer_auth',
            host: 'http://www.shopware.com',
            transports: ['default'],
        } }); // start AdminWorker
        await jest.runAllTimers(); // start consumeMessages
        await jest.runAllTimers(); // consume firstMessage

        expect(getConsumeRequests(axiosMock.history)).toHaveLength(1);

        // should retry after 20 seconds
        await jest.runTimersToTime(19999);
        expect(getConsumeRequests(axiosMock.history)).toHaveLength(1);
        await jest.runTimersToTime(1);
        expect(getConsumeRequests(axiosMock.history)).toHaveLength(2);
    });

    it('should restart the consume call directly when handledMessages exist', async () => {
        axiosMock.reset();
        axiosMock.onAny().reply(() => {
            return [200, { handledMessages: 50 }];
        });

        expect(getConsumeRequests(axiosMock.history)).toHaveLength(0);

        await AdminWorker.onMessage({ data: {
            context: {
                languageId: 'language_id',
                apiResourcePath: 'api_resource_path',
            },
            bearerAuth: 'bearer_auth',
            host: 'http://www.shopware.com',
            transports: ['default'],
        } }); // start AdminWorker
        await jest.runAllTimers(); // start consumeMessages
        await jest.runAllTimers(); // consume firstMessage

        expect(getConsumeRequests(axiosMock.history)).toHaveLength(1);

        // should retry after 20 seconds
        await jest.runTimersToTime(0);
        expect(getConsumeRequests(axiosMock.history)).toHaveLength(2);
    });
});
