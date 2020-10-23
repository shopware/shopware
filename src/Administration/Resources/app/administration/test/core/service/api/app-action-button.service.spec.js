import AppActionButtonService, { InvalidActionButtonParameterError } from 'src/core/service/api/app-action-button.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createAppActionButtonService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);
    const appActionButtonService = new AppActionButtonService(client, loginService);
    return { appActionButtonService, clientMock };
}


describe('appActionButtonService', () => {
    it('is registered correctly', () => {
        // const appActionButtonService = Shopware.Service('appActionButton');
        const { appActionButtonService } = createAppActionButtonService();

        expect(appActionButtonService).toBeInstanceOf(AppActionButtonService);
    });

    it('throws an exception if entity is not set for getActionButtonsPerView', () => {
        // const appActionButtonService = Shopware.Service('appActionButton');
        const { appActionButtonService } = createAppActionButtonService();

        expect(() => {
            appActionButtonService.getActionButtonsPerView();
        }).toThrow(
            new InvalidActionButtonParameterError('Parameter "entity" must have a valid value. Given: undefined')
        );
    });

    it('throws an exception if view is not set for getActionButtonsPerView', () => {
        // const appActionButtonService = Shopware.Service('appActionButton');
        const { appActionButtonService } = createAppActionButtonService();

        expect(() => {
            appActionButtonService.getActionButtonsPerView('product');
        }).toThrow(
            new InvalidActionButtonParameterError('Parameter "view" must have a valid value. Given: undefined')
        );
    });

    it('returns action button data', async () => {
        // const appActionButtonService = Shopware.Service('appActionButton');
        const { appActionButtonService, clientMock } = createAppActionButtonService();

        clientMock.onGet('app-system/action-button/product/detail').reply(
            200,
            {
                actions: [{
                    name: 'App'
                }]
            }
        );

        const actionButtons = await appActionButtonService.getActionButtonsPerView('product', 'detail');

        expect(actionButtons).toEqual([{
            name: 'App'
        }]);
    });

    it('calls the correct api endpoint to run an action', async () => {
        // const appActionButtonService = Shopware.Service('appActionButton');
        const { appActionButtonService, clientMock } = createAppActionButtonService();
        const actionButtonId = Shopware.Utils.createId();

        clientMock.onPost(`app-system/action-button/run/${actionButtonId}`).reply(
            200,
            null
        );

        await appActionButtonService.runAction(actionButtonId);
    });
});
