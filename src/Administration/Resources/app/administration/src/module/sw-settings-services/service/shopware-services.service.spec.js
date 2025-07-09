import createHTTPClient from 'src/core/factory/http.factory';
import createLoginService from 'src/core/service/login.service';
import MockAdapter from 'axios-mock-adapter';
import ShopwareServicesService from './shopware-services.service';
import SystemConfigApiService from '../../../core/service/api/system-config.api.service';

describe('src/module/sw-settings-services/service/shopware-services-service.ts', () => {
    it.each([
        [
            undefined,
            'en-US',
            'en-US',
        ],
        [
            'de-DE',
            'en-US',
            'de-DE',
        ],
    ])(
        'loads installed services using the correct language',
        async (sessionLanguage, apiContextLanguage, expectedLanguage) => {
            Shopware.Store.get('session').languageId = sessionLanguage;
            Shopware.Context.api.languageId = apiContextLanguage;

            const client = createHTTPClient();
            const clientMock = new MockAdapter(client);
            const loginService = createLoginService(client, Shopware.Context.api);
            const systemConfigService = jest.fn();
            const shopwareServicesService = new ShopwareServicesService(client, loginService, systemConfigService);

            clientMock.onGet('service/list').reply(200, [
                { name: 'Service1', active: true },
                { name: 'Service2', active: false },
                { name: 'Service3', active: true },
            ]);

            const installedServices = await shopwareServicesService.getInstalledServices();

            expect(installedServices).toHaveLength(3);
            expect(clientMock.history.get).toHaveLength(1);
            expect(clientMock.history.get[0].headers['sw-language-id']).toBe(expectedLanguage);
        },
    );

    it.each([
        [
            undefined,
            undefined,
            undefined,
            undefined,
        ],
        [
            true,
            undefined,
            true,
            undefined,
        ],
        [
            false,
            undefined,
            false,
            undefined,
        ],
        [
            true,
            JSON.stringify({
                identifier: 'identifier',
                revision: '2025-07-07',
                consentingUserId: 'consenting-user-id',
                grantedAt: '2025-07-07T00:00:00.000Z',
            }),
            true,
            {
                identifier: 'identifier',
                revision: '2025-07-07',
                consentingUserId: 'consenting-user-id',
                grantedAt: '2025-07-07T00:00:00.000Z',
            },
        ],
    ])(
        'loads the services context',
        async (
            configValueDisabled,
            configValuePermissionsConsent,
            expectedValueDisabled,
            expectedValuePermissionsConsent,
        ) => {
            const client = createHTTPClient();
            const clientMock = new MockAdapter(client);
            const loginService = createLoginService(client, Shopware.Context.api);
            const systemConfigService = new SystemConfigApiService(client, loginService);
            const shopwareServicesService = new ShopwareServicesService(client, loginService, systemConfigService);

            clientMock.onGet('_action/system-config').reply(200, {
                'core.services.disabled': configValueDisabled,
                'core.services.permissionsConsent': configValuePermissionsConsent,
            });

            const servicesContext = await shopwareServicesService.getServicesContext();

            expect(servicesContext.disabled).toBe(expectedValueDisabled);
            expect(servicesContext.permissionsConsent).toEqual(expectedValuePermissionsConsent);
        },
    );

    it('accepts permissions revision', async () => {
        const client = createHTTPClient();
        const clientMock = new MockAdapter(client);
        const loginService = createLoginService(client, Shopware.Context.api);
        const systemConfigService = new SystemConfigApiService(client, loginService);
        const shopwareServicesService = new ShopwareServicesService(client, loginService, systemConfigService);

        const revision = '2025-07-07';

        clientMock.onPost(`services/permissions/grant/${revision}`).reply(204, {
            success: true,
        });

        clientMock.onGet('_action/system-config').reply(200, {
            'core.services.disabled': undefined,
            'core.services.permissionsConsent': undefined,
        });

        await shopwareServicesService.acceptRevision(revision);

        expect(clientMock.history.post).toHaveLength(1);
        expect(clientMock.history.post[0].url).toBe('services/permissions/grant/2025-07-07');
        expect(clientMock.history.get).toHaveLength(1);
        expect(clientMock.history.get[0].url).toBe('_action/system-config');
    });

    it('revokes permissions', async () => {
        const client = createHTTPClient();
        const clientMock = new MockAdapter(client);
        const loginService = createLoginService(client, Shopware.Context.api);
        const systemConfigService = new SystemConfigApiService(client, loginService);
        const shopwareServicesService = new ShopwareServicesService(client, loginService, systemConfigService);

        clientMock.onPost('services/permissions/revoke').reply(204, {
            success: true,
        });

        clientMock.onGet('_action/system-config').reply(200, {
            'core.services.disabled': undefined,
            'core.services.permissionsConsent': undefined,
        });

        await shopwareServicesService.revokePermissions();

        expect(clientMock.history.post).toHaveLength(1);
        expect(clientMock.history.post[0].url).toBe('services/permissions/revoke');
        expect(clientMock.history.get).toHaveLength(1);
        expect(clientMock.history.get[0].url).toBe('_action/system-config');
    });

    it.each([
        ['enable'],
        ['disable'],
    ])('enables and disables all services', async (action) => {
        const client = createHTTPClient();
        const clientMock = new MockAdapter(client);
        const loginService = createLoginService(client, Shopware.Context.api);
        const systemConfigService = new SystemConfigApiService(client, loginService);
        const shopwareServicesService = new ShopwareServicesService(client, loginService, systemConfigService);

        clientMock.onPost(`services/${action}`).reply(204, {
            success: true,
        });

        clientMock.onGet('_action/system-config').reply(200, {
            'core.services.disabled': undefined,
            'core.services.permissionsConsent': undefined,
        });

        if (action === 'disable') {
            await shopwareServicesService.disableAllServices();
        }

        if (action === 'enable') {
            await shopwareServicesService.enableAllServices();
        }

        expect(clientMock.history.post).toHaveLength(1);
        expect(clientMock.history.post[0].url).toBe(`services/${action}`);
        expect(clientMock.history.get).toHaveLength(1);
        expect(clientMock.history.get[0].url).toBe('_action/system-config');
    });
});
