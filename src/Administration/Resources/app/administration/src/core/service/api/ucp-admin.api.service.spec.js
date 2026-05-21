/**
 * @sw-package fundamentals@framework
 */

import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';
import UcpAdminApiService from './ucp-admin.api.service';

function getUcpAdminApiService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);

    const ucpAdminService = new UcpAdminApiService(client, loginService);
    return { ucpAdminService, clientMock };
}

describe('ucpAdminService', () => {
    it('is registered correctly', () => {
        const { ucpAdminService } = getUcpAdminApiService();
        expect(ucpAdminService).toBeInstanceOf(UcpAdminApiService);
        expect(ucpAdminService.name).toBe('ucpAdminService');
    });

    it('listSalesChannels GETs /_admin/ucp/sales-channels', async () => {
        const { ucpAdminService, clientMock } = getUcpAdminApiService();
        clientMock.onGet('/_admin/ucp/sales-channels').reply(200, { items: [] });

        const response = await ucpAdminService.listSalesChannels();
        expect(response).toEqual({ items: [] });
    });

    it('getConfig GETs /_admin/ucp/sales-channels/{id}/config', async () => {
        const { ucpAdminService, clientMock } = getUcpAdminApiService();
        clientMock.onGet('/_admin/ucp/sales-channels/sc-1/config').reply(200, { active: true });

        const response = await ucpAdminService.getConfig('sc-1');
        expect(response).toEqual({ active: true });
    });

    it('writeConfig PUTs the payload to /_admin/ucp/sales-channels/{id}/config', async () => {
        const { ucpAdminService, clientMock } = getUcpAdminApiService();
        const payload = { active: false, signaturePolicy: 'strict' };
        clientMock.onPut('/_admin/ucp/sales-channels/sc-1/config').reply(204);

        await ucpAdminService.writeConfig('sc-1', payload);

        expect(clientMock.history.put).toHaveLength(1);
        expect(JSON.parse(clientMock.history.put[0].data)).toEqual(payload);
    });

    it('createKey POSTs algorithm and rotate to the keys endpoint', async () => {
        const { ucpAdminService, clientMock } = getUcpAdminApiService();
        clientMock.onPost('/_admin/ucp/sales-channels/sc-1/keys').reply(200, { kid: 'kid-1' });

        const response = await ucpAdminService.createKey('sc-1', { algorithm: 'ES384', rotate: false });

        expect(response).toEqual({ kid: 'kid-1' });
        expect(JSON.parse(clientMock.history.post[0].data)).toEqual({ algorithm: 'ES384', rotate: false });
    });

    it('retireKey POSTs to the retire endpoint', async () => {
        const { ucpAdminService, clientMock } = getUcpAdminApiService();
        clientMock.onPost('/_admin/ucp/sales-channels/sc-1/keys/kid-1/retire').reply(204);

        await expect(ucpAdminService.retireKey('sc-1', 'kid-1')).resolves.not.toThrow();
    });

    it('deleteKey DELETEs the key endpoint', async () => {
        const { ucpAdminService, clientMock } = getUcpAdminApiService();
        clientMock.onDelete('/_admin/ucp/sales-channels/sc-1/keys/kid-1').reply(204);

        await expect(ucpAdminService.deleteKey('sc-1', 'kid-1')).resolves.not.toThrow();
    });

    it('previewProfile GETs the profile-preview endpoint', async () => {
        const { ucpAdminService, clientMock } = getUcpAdminApiService();
        clientMock.onGet('/_admin/ucp/sales-channels/sc-1/profile-preview').reply(200, { version: '2026-01-23' });

        const response = await ucpAdminService.previewProfile('sc-1');
        expect(response).toEqual({ version: '2026-01-23' });
    });

    it('listPlatformProfiles GETs /_admin/ucp/platform-profiles', async () => {
        const { ucpAdminService, clientMock } = getUcpAdminApiService();
        clientMock.onGet('/_admin/ucp/platform-profiles').reply(200, { items: [] });

        const response = await ucpAdminService.listPlatformProfiles();
        expect(response).toEqual({ items: [] });
    });

    it('deletePlatformProfile DELETEs the cache entry', async () => {
        const { ucpAdminService, clientMock } = getUcpAdminApiService();
        clientMock.onDelete('/_admin/ucp/platform-profiles/pp-1').reply(204);

        await expect(ucpAdminService.deletePlatformProfile('pp-1')).resolves.not.toThrow();
    });
});
