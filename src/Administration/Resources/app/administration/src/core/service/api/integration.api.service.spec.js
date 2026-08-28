/**
 * @sw-package fundamentals@framework
 */

import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';
import IntegrationApiService from './integration.api.service';

function getIntegrationApiService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);

    const integrationApiService = new IntegrationApiService(client, loginService);
    return { integrationApiService, clientMock };
}

describe('integrationApiService', () => {
    it('is registered correctly', () => {
        const { integrationApiService } = getIntegrationApiService();
        expect(integrationApiService).toBeInstanceOf(IntegrationApiService);
        expect(integrationApiService.name).toBe('integrationService');
    });

    it('generateKey sends request to correct endpoint', async () => {
        const { integrationApiService, clientMock } = getIntegrationApiService();

        clientMock.onGet('/_action/access-key/intergration').reply(200, {
            accessKey: 'SWIA123',
            secretAccessKey: 'secret123',
        });

        const response = await integrationApiService.generateKey();

        expect(response.accessKey).toBe('SWIA123');
        expect(response.secretAccessKey).toBe('secret123');
    });

    it('saveMcpAllowlist sends POST to correct endpoint with allowlist', async () => {
        const { integrationApiService, clientMock } = getIntegrationApiService();
        const integrationId = 'abc123';
        const allowlist = [
            'shopware-entity-read',
            'shopware-entity-search',
        ];

        clientMock.onPost(`/_action/integration/${integrationId}/mcp-allowlist`).reply(204, null);

        await expect(integrationApiService.saveMcpAllowlist(integrationId, allowlist)).resolves.not.toThrow();

        expect(clientMock.history.post).toHaveLength(1);
        expect(JSON.parse(clientMock.history.post[0].data)).toEqual({ allowlist });
    });

    it('saveMcpAllowlist sends null allowlist correctly', async () => {
        const { integrationApiService, clientMock } = getIntegrationApiService();
        const integrationId = 'abc123';

        clientMock.onPost(`/_action/integration/${integrationId}/mcp-allowlist`).reply(204, null);

        await expect(integrationApiService.saveMcpAllowlist(integrationId, null)).resolves.not.toThrow();

        expect(JSON.parse(clientMock.history.post[0].data)).toEqual({ allowlist: null });
    });

    it('updateAdmin sends PATCH to integration endpoint', async () => {
        const { integrationApiService, clientMock } = getIntegrationApiService();
        const integrationId = 'abc123';

        clientMock.onPatch(`integration/${integrationId}`).reply(204, null);

        await expect(integrationApiService.updateAdmin(integrationId, true)).resolves.not.toThrow();

        expect(clientMock.history.patch).toHaveLength(1);
        expect(JSON.parse(clientMock.history.patch[0].data)).toEqual({ admin: true });
    });
});
