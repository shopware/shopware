/**
 * @internal
 *
 * @sw-package framework
 */
import MockAdapter from 'axios-mock-adapter';
import SsoSettingsService from './sso-settings.service';
import createHTTPClient from '../../factory/http.factory';
import createLoginService from '../login.service';

function createSsoSettingService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);

    const loginService = createLoginService(clientMock, Shopware.Context.api);

    const service = new SsoSettingsService(client, loginService);

    return {
        service,
        clientMock,
    };
}

describe('core/service/api/sso-settings.service.js', () => {
    it('should return isSso info with true value', async () => {
        const { service, clientMock } = createSsoSettingService();

        clientMock.onGet('/api/_info/is-sso').reply(200, {
            isSso: true,
        });

        const isSso = await service.isSso();

        expect(isSso.isSso).toBe(true);
    });

    it('should return isSso info with false value', async () => {
        const { service, clientMock } = createSsoSettingService();

        clientMock.onGet('/api/_info/is-sso').reply(200, {
            isSso: false,
        });

        const isSso = await service.isSso();

        expect(isSso.isSso).toBe(false);
    });
});
