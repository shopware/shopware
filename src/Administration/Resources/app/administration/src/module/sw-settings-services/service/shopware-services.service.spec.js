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
        ],
        [
            true,
            true,
        ],
        [
            false,
            false,
        ],
    ])('loads the services context', async (configValueDisabled, expectedValueDisabled) => {
        const client = createHTTPClient();
        const clientMock = new MockAdapter(client);
        const loginService = createLoginService(client, Shopware.Context.api);
        const systemConfigService = new SystemConfigApiService(client, loginService);
        const shopwareServicesService = new ShopwareServicesService(client, loginService, systemConfigService);

        clientMock.onGet('_action/system-config').reply(200, {
            'core.services.disabled': configValueDisabled,
        });

        const servicesContext = await shopwareServicesService.getServicesContext();

        expect(servicesContext.disabled).toBe(expectedValueDisabled);
    });

    it('loads consent revision metadata', async () => {
        const client = createHTTPClient();
        const clientMock = new MockAdapter(client);
        const loginService = createLoginService(client, Shopware.Context.api);
        const systemConfigService = new SystemConfigApiService(client, loginService);
        const shopwareServicesService = new ShopwareServicesService(client, loginService, systemConfigService);

        clientMock.onGet('services/consent-revision').reply(200, {
            'latest-revision': '2025-07-07',
            'available-revisions': [
                {
                    revision: '2025-07-07',
                    links: {
                        'feedback-url': 'https://example.com/feedback',
                        'docs-url': 'https://example.com/docs',
                        'tos-url': 'https://example.com/tos',
                    },
                },
            ],
        });

        const revisions = await shopwareServicesService.getConsentRevision('de-DE');

        expect(revisions['latest-revision']).toBe('2025-07-07');
        expect(clientMock.history.get).toHaveLength(1);
        expect(clientMock.history.get[0].headers['Accept-Language']).toBe('de-DE');
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
        });

        if (action === 'disable') {
            await shopwareServicesService.disableAllServices();
        }

        if (action === 'enable') {
            await shopwareServicesService.enableAllServices();
        }

        expect(clientMock.history.post).toHaveLength(1);
        expect(clientMock.history.post[0].url).toBe(`services/${action}`);
    });

    it('returns categorized permissions', async () => {
        const client = createHTTPClient();
        const clientMock = new MockAdapter(client);
        const loginService = createLoginService(client, Shopware.Context.api);
        const systemConfigService = new SystemConfigApiService(client, loginService);
        const shopwareServicesService = new ShopwareServicesService(client, loginService, systemConfigService);

        clientMock.onGet(`services/categorized-permissions/MyCoolService`).reply(200, {
            permissions: {
                user: [
                    {
                        entity: 'admin_user',
                        operation: 'read',
                    },
                ],
            },
        });

        const categorizedPermissions = await shopwareServicesService.getCategorizedPermissions('MyCoolService');

        expect(categorizedPermissions).toEqual({
            permissions: {
                user: [
                    {
                        entity: 'admin_user',
                        operation: 'read',
                    },
                ],
            },
        });
    });
});
