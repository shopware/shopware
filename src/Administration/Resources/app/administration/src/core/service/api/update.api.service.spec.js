import UpdateApiService from 'src/core/service/api/update.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createUpdateApiService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);
    const updateApiService = new UpdateApiService(client, loginService);
    return { updateApiService, clientMock };
}

/**
 * @package system-settings
 */
describe('updateApiService', () => {
    it('is registered correctly', async () => {
        const { updateApiService } = createUpdateApiService();

        expect(updateApiService).toBeInstanceOf(UpdateApiService);
    });

    it('test check for updates', async () => {
        const { updateApiService, clientMock } = createUpdateApiService();

        clientMock.onGet('/_action/update/check').reply(
            200,
            {
                success: true,
            },
        );

        const result = await updateApiService.checkForUpdates();

        expect(result).toEqual({
            success: true,
        });
    });

    it('test requirements', async () => {
        const { updateApiService, clientMock } = createUpdateApiService();

        clientMock.onGet('/_action/update/check-requirements').reply(
            200,
            {
                success: true,
            },
        );

        const result = await updateApiService.checkRequirements();

        expect(result).toEqual({
            success: true,
        });
    });

    it('test extensionCompatibility', async () => {
        const { updateApiService, clientMock } = createUpdateApiService();

        clientMock.onGet('/_action/update/extension-compatibility').reply(
            200,
            {
                success: true,
            },
        );

        const result = await updateApiService.extensionCompatibility();

        expect(result).toEqual({
            success: true,
        });
    });

    it('test downloadRecovery', async () => {
        const { updateApiService, clientMock } = createUpdateApiService();

        clientMock.onGet('/_action/update/download-recovery').reply(
            200,
            {
                success: true,
            },
        );

        const result = await updateApiService.downloadRecovery();

        expect(result).toEqual({
            success: true,
        });
    });

    it('test deactivatePlugins', async () => {
        const { updateApiService, clientMock } = createUpdateApiService();

        clientMock.onGet('/_action/update/deactivate-plugins?offset=0&deactivationFilter=foo').reply(
            200,
            {
                success: true,
            },
        );

        const result = await updateApiService.deactivatePlugins(0, 'foo');

        expect(result).toEqual({
            success: true,
        });
    });
});
