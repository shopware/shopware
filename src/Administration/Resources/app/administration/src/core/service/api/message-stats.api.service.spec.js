/**
 * @sw-package framework
 */

import MessageStatsApiService from 'src/core/service/api/message-stats.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createMessageStatsApiService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);
    const messageStatsApiService = new MessageStatsApiService(client, loginService);
    return { messageStatsApiService, clientMock };
}

describe('messageStatsApiService', () => {
    it('is registered correctly', async () => {
        const { messageStatsApiService } = createMessageStatsApiService();

        expect(messageStatsApiService).toBeInstanceOf(MessageStatsApiService);
    });

    it('gets message statistics correctly when enabled and stats are available', async () => {
        const { messageStatsApiService, clientMock } = createMessageStatsApiService();

        const mockResponse = {
            enabled: true,
            stats: {
                totalMessagesProcessed: 6,
                processedSince: '2025-05-02T15:25:32.000+00:00',
                averageTimeInQueue: 7.5,
                messageTypeStats: [
                    {
                        type: 'Shopware\\Core\\Framework\\Adapter\\Cache\\InvalidateCacheTask',
                        count: 2,
                    },
                    {
                        type: 'Shopware\\Core\\Content\\ProductExport\\ScheduledTask\\ProductExportGenerateTask',
                        count: 2,
                    },
                    {
                        type: 'Shopware\\Elasticsearch\\Framework\\Indexing\\CreateAliasTask',
                        count: 1,
                    },
                ],
            },
        };

        clientMock.onGet('/_info/message-stats.json').reply(200, mockResponse);

        const stats = await messageStatsApiService.getStats();

        expect(stats).toEqual(mockResponse);
    });

    it('gets message statistics correctly when enabled but no stats are available', async () => {
        const { messageStatsApiService, clientMock } = createMessageStatsApiService();

        const mockResponse = {
            enabled: true,
            stats: null,
        };

        clientMock.onGet('/_info/message-stats.json').reply(200, mockResponse);

        const stats = await messageStatsApiService.getStats();

        expect(stats).toEqual(mockResponse);
    });

    it('gets message statistics correctly when disabled', async () => {
        const { messageStatsApiService, clientMock } = createMessageStatsApiService();

        const mockResponse = {
            enabled: false,
            stats: null,
        };

        clientMock.onGet('/_info/message-stats.json').reply(200, mockResponse);

        const stats = await messageStatsApiService.getStats();

        expect(stats).toEqual(mockResponse);
    });

    it('handles API errors correctly', async () => {
        const { messageStatsApiService, clientMock } = createMessageStatsApiService();

        clientMock.onGet('/_info/message-stats.json').reply(500, {
            errors: [
                {
                    code: '0',
                    status: '500',
                    title: 'Internal Server Error',
                    detail: 'An error occurred while processing the request',
                },
            ],
        });

        await expect(messageStatsApiService.getStats()).rejects.toThrow();
    });
});
