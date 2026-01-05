/**
 * @sw-package framework
 */
import AppPrivilegesService from 'src/core/service/api/app-privileges.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createAppPrivilegesService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);
    const appPrivilegesService = new AppPrivilegesService(client, loginService);
    return { appPrivilegesService, clientMock };
}

describe('appPrivilegesService', () => {
    it('is registered correctly', async () => {
        const { appPrivilegesService } = createAppPrivilegesService();

        expect(appPrivilegesService).toBeInstanceOf(AppPrivilegesService);
    });

    it('accepts privileges correctly', async () => {
        const { appPrivilegesService, clientMock } = createAppPrivilegesService();

        clientMock.onPatch('/app-system/SomeApp/privileges', { accept: ['product:read'] }).reply(200, {});

        await appPrivilegesService.acceptPrivileges('SomeApp', ['product:read']);

        expect(clientMock.history.patch).toHaveLength(1);
    });
});
