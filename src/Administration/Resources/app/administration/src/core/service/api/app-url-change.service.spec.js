/**
 * @package admin
 */
import AppUrlChangeService from 'src/core/service/api/app-url-change.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createAppUrlChangeService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);
    const appUrlChangeService = new AppUrlChangeService(client, loginService);
    return { appUrlChangeService, clientMock };
}

describe('appUrlChangeService', () => {
    it('is registered correctly', async () => {
        // Shopware.Service('appUrlChangeService')
        const { appUrlChangeService } = createAppUrlChangeService();

        expect(appUrlChangeService).toBeInstanceOf(AppUrlChangeService);
    });

    it('fetches strategies correctly', async () => {
        // Shopware.Service('appUrlChangeService')
        const { appUrlChangeService, clientMock } = createAppUrlChangeService();

        clientMock.onGet('/app-system/app-url-change/strategies').reply(200, {
            first: 'a',
            second: 'b',
            third: 'c',
        });

        const strategies = await appUrlChangeService.fetchResolverStrategies();

        expect(strategies).toEqual([
            {
                name: 'first',
                description: 'a',
            },
            {
                name: 'second',
                description: 'b',
            },
            {
                name: 'third',
                description: 'c',
            },
        ]);
    });

    it('sends name of selected strategy', async () => {
        // Shopware.Service('appUrlChangeService')
        const { appUrlChangeService, clientMock } = createAppUrlChangeService();

        clientMock
            .onPost('app-system/app-url-change/resolve', {
                strategy: 'selectedStrategy',
            })
            .reply(204);

        await appUrlChangeService.resolveUrlChange({
            name: 'selectedStrategy',
        });

        expect(JSON.parse(clientMock.history.post[0].data)).toEqual({
            strategy: 'selectedStrategy',
        });
    });

    it('returns old and new url', async () => {
        // Shopware.Service('appUrlChangeService')
        const { appUrlChangeService, clientMock } = createAppUrlChangeService();

        clientMock.onGet('app-system/app-url-change/url-difference').reply(200, {
            oldUrl: 'http://old',
            newUrl: 'http://new',
        });

        const urlDiff = await appUrlChangeService.getUrlDiff();

        expect(urlDiff).toEqual({
            oldUrl: 'http://old',
            newUrl: 'http://new',
        });
    });

    it('returns null if getUrlDiff has no content', async () => {
        // Shopware.Service('appUrlChangeService')
        const { appUrlChangeService, clientMock } = createAppUrlChangeService();

        clientMock.onGet('app-system/app-url-change/url-difference').reply(204);

        const urlDiff = await appUrlChangeService.getUrlDiff();

        expect(urlDiff).toBeNull();
    });
});
