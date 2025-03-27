/**
 * @sw-package framework
 */
import AppPermissionsService from 'src/core/service/api/app-permissions.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createAppPermissionsService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);
    const appPermissionsService = new AppPermissionsService(client, loginService);
    return { appPermissionsService, clientMock };
}

describe('appPermissionsService', () => {
    it('is registered correctly', async () => {
        // Shopware.Service('appPermissionsService')
        const { appPermissionsService } = createAppPermissionsService();

        expect(appPermissionsService).toBeInstanceOf(AppPermissionsService);
    });

    it('accepts permissions correctly', async () => {
        // Shopware.Service('appPermissionsService')
        const { appPermissionsService, clientMock } = createAppPermissionsService();

        clientMock
            .onPost('/app-system/SomeApp/permissions/accept', ['product:read'])
            .reply(200, {});

        await appPermissionsService.acceptPermissions('SomeApp', ['product:read']);

        expect(clientMock.history.post).toHaveLength(1);
    });
});
