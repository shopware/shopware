/**
 * @sw-package framework
 */
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';
import ShopIdChangeService from './shop-id-change.service';

function createShopIdChangeService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);
    const shopIdChangeService = new ShopIdChangeService(client, loginService);
    return { shopIdChangeService: shopIdChangeService, clientMock };
}

describe('shopIdChangeService', () => {
    it('is registered correctly', async () => {
        const { shopIdChangeService } = createShopIdChangeService();

        expect(shopIdChangeService).toBeInstanceOf(ShopIdChangeService);
    });

    it('fetches strategies correctly', async () => {
        const { shopIdChangeService, clientMock } = createShopIdChangeService();

        clientMock.onGet('/app-system/shop-id/change-strategies').reply(200, {
            first: 'a',
            second: 'b',
            third: 'c',
        });

        const strategies = await shopIdChangeService.getChangeStrategies();

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
        const { shopIdChangeService, clientMock } = createShopIdChangeService();

        clientMock
            .onPost('app-system/shop-id/change', {
                strategy: 'selectedStrategy',
            })
            .reply(204);

        await shopIdChangeService.changeShopId({
            name: 'selectedStrategy',
        });

        expect(JSON.parse(clientMock.history.post[0].data)).toEqual({
            strategy: 'selectedStrategy',
        });
    });

    it('returns shop ID check', async () => {
        const { shopIdChangeService, clientMock } = createShopIdChangeService();

        const response = {
            fingerprints: {
                matchingFingerprints: {
                    app_url: {
                        storedStamp: 'https://old.url',
                    },
                },
                mismatchingFingerprints: {
                    installation_path: {
                        storedStamp: '/var/www/old',
                        expectedStamp: '/var/www/new',
                    },
                    sales_channel_domain_urls: {
                        storedStamp: '4f02235821f19784fd6ea6a6df754552',
                        expectedStamp: '14f15f8c18172be22c9135c278358549',
                    },
                },
                score: 225,
                threshold: 75,
            },
            apps: [
                'Test Foo App',
                'Test Bar App',
                'Test Baz App',
            ],
        };

        clientMock.onPost('app-system/shop-id/check').reply(200, response);

        const fingerprints = await shopIdChangeService.checkShopId();

        expect(fingerprints).toEqual(response);
    });

    it('returns null if shop id fingerprints have not changed', async () => {
        const { shopIdChangeService, clientMock } = createShopIdChangeService();

        clientMock.onPost('app-system/shop-id/check').reply(204);

        const urlDiff = await shopIdChangeService.checkShopId();

        expect(urlDiff).toBeNull();
    });
});
