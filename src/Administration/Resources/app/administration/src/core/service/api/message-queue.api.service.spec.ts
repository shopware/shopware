/**
 * @sw-package framework
 */

import MockAdapter from 'axios-mock-adapter';
import MessageQueueApiService from 'src/core/service/api/message-queue.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import { deprecations } from 'test/_helper_/deprecations';

function createMessageQueueService() {
    const context = Shopware.Context.api;
    const client = createHTTPClient(context);
    const clientMock = new MockAdapter(client);
    const messageQueueService = new MessageQueueApiService(client, createLoginService(client, context));

    clientMock.onPost('/_action/message-queue/consume').reply(200, { handledMessages: 3 });

    return { client, clientMock, messageQueueService };
}

describe('core/service/api/message-queue.api.service', () => {
    it('should consume the given receiver', async () => {
        const { clientMock, messageQueueService } = createMessageQueueService();

        await expect(messageQueueService.consume('default')).resolves.toEqual({ handledMessages: 3 });
        expect(JSON.parse(clientMock.history.post[0].data as string)).toEqual({ receiver: 'default' });
    });

    it('should cancel the consume request through an AbortSignal', async () => {
        const { client, clientMock, messageQueueService } = createMessageQueueService();
        const controller = new AbortController();
        controller.abort();

        const error = await messageQueueService.consume('default', controller.signal).catch((e: unknown) => e);

        expect(client.isCancel(error)).toBe(true);
        expect(clientMock.history.post).toHaveLength(0);
    });

    // @deprecated tag:v6.8.0 - Remove together with the CancelToken shim.
    it.deprecated('v6.8.0.0')('should still cancel the consume request through a CancelToken', async () => {
        const { client, clientMock, messageQueueService } = createMessageQueueService();
        const source = client.CancelToken.source();
        source.cancel();

        const error = await messageQueueService.consume('default', source.token).catch((e: unknown) => e);

        expect(client.isCancel(error)).toBe(true);
        expect(clientMock.history.post).toHaveLength(0);
        expect(deprecations).toHaveTriggered('"httpClient.CancelToken" and the "cancelToken" request option');
    });
});
