/**
 * @package admin
 */
import AppModulesService from 'src/core/service/api/app-modules.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createAppModulesService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);
    const appModulesService = new AppModulesService(client, loginService);
    return { appModulesService, clientMock };
}

const exampleModule = {
    name: 'SwagExampleApp',
    label: {
        'en-GB': 'Swag Example App',
        'de-DE': 'Swag Example App',
    },
    modules: [
        {
            name: 'orderList',
            label: {
                'de-DE': 'Bestellliste',
                'en-GB': 'Order list',
            },
            /* eslint-disable-next-line */
            source: 'example/iframe/orderlist?shop-id=L5RW86IMxHFLkj4S&shop-url=http://localhost:8000&timestamp=1602699100&shopware-shop-signature=b00fd4a7d90616ff49580b78b9ad4f3855d7c11b673ccc0d2894daeb3caa1d04'
        },
    ],
};

describe('appModulesService', () => {
    it('is registered correctly', async () => {
        // const appModulesService = Shopware.Service('appActionButton');
        const { appModulesService } = createAppModulesService();

        expect(appModulesService).toBeInstanceOf(AppModulesService);
    });

    it('fetches modules correctly', async () => {
        // const appModulesService = Shopware.Service('appActionButton');
        const { appModulesService, clientMock } = createAppModulesService();

        clientMock.onGet('/app-system/modules').reply(
            200,
            {
                modules: [exampleModule],
            },
        );

        const modules = await appModulesService.fetchAppModules();

        expect(modules).toEqual([exampleModule]);
    });
});
