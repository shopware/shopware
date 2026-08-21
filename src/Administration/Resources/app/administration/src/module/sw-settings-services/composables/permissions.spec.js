import { createPinia, setActivePinia } from 'pinia';
import * as permissions from './permissions';
import { useShopwareServicesStore } from '../store/shopware-services.store';

describe('src/module/sw-settings-services/composables/permissions', () => {
    let reloadMock;

    beforeAll(() => {
        Shopware.Service().register('shopwareServicesService', () => ({
            acceptRevision: jest.fn(),
            revokePermissions: jest.fn(),
            getServicesContext: jest.fn(async () => ({ disabled: false, permissionsConsent: undefined })),
        }));
        Shopware.Service().register('serviceRegistryClient', () => ({
            getCurrentRevision: jest.fn(async () => ({
                'latest-revision': '2025-06-25',
                'available-revisions': [],
            })),
        }));
        reloadMock = jest.fn();
        permissions.__setReloadFn(reloadMock);
    });

    beforeEach(() => {
        reloadMock.mockClear();
        setActivePinia(createPinia());
        useShopwareServicesStore();
    });

    it('calls shopware service and reloads', async () => {
        const shopwareServicesStore = useShopwareServicesStore();

        shopwareServicesStore.revisions = {
            'latest-revision': '2025-06-25',
            'available-revisions': [
                {
                    revision: '2025-06-25',
                    links: {},
                },
            ],
        };

        await permissions.grantPermissions();

        expect(Shopware.Service('shopwareServicesService').acceptRevision).toHaveBeenCalledWith('2025-06-25');
        expect(reloadMock).toHaveBeenCalled();
    });

    it('throws exception if there is no current revision', async () => {
        await expect(() => permissions.grantPermissions()).rejects.toThrow(new Error('No revision available'));
    });

    it('calls shopware service to revoke permissions and reloads', async () => {
        await permissions.revokePermissions();

        expect(Shopware.Service('shopwareServicesService').revokePermissions).toHaveBeenCalled();
        expect(reloadMock).toHaveBeenCalled();
    });

    it('grants permissions and reloads the Administration for a Service SDK request', async () => {
        const shopwareServicesStore = useShopwareServicesStore();
        shopwareServicesStore.revisions = {
            'latest-revision': '2025-06-25',
            'available-revisions': [
                {
                    revision: '2025-06-25',
                    links: {},
                },
            ],
        };

        Shopware.Store.get('extensions').extensionsState = {
            SwagCopilot: {
                name: 'SwagCopilot',
                baseUrl: 'https://copilot.staging-apps.shopware.io',
                permissions: {},
                type: 'app',
                sourceType: 'service',
            },
        };

        await permissions.grantPermissionsFromSdk(
            {},
            { _event_: new MessageEvent('message', { origin: 'https://copilot.staging-apps.shopware.io' }) },
        );

        expect(Shopware.Service('shopwareServicesService').acceptRevision).toHaveBeenCalledWith('2025-06-25');
        expect(reloadMock).toHaveBeenCalled();
    });

    it('rejects SDK permission grants from non-Service origins', () => {
        Shopware.Store.get('extensions').extensionsState = {
            SomeApp: {
                name: 'SomeApp',
                baseUrl: 'https://app.example.com',
                permissions: {},
                type: 'app',
                sourceType: null,
            },
        };

        expect(() =>
            permissions.grantPermissionsFromSdk(
                {},
                { _event_: new MessageEvent('message', { origin: 'https://app.example.com' }) },
            ),
        ).toThrow('Only Shopware Services can grant permissions.');
    });

    it('reports permission as granted when Shopware Services are disabled', async () => {
        const shopwareServicesStore = useShopwareServicesStore();
        shopwareServicesStore.config = { disabled: true };

        await expect(permissions.isPermissionGrantedFromSdk()).resolves.toBe(true);
    });

    it('reports permission as granted when the latest revision has been consented to', async () => {
        const shopwareServicesStore = useShopwareServicesStore();
        shopwareServicesStore.config = {
            disabled: false,
            permissionsConsent: { revision: '2025-06-25' },
        };
        shopwareServicesStore.revisions = {
            'latest-revision': '2025-06-25',
            'available-revisions': [],
        };

        await expect(permissions.isPermissionGrantedFromSdk()).resolves.toBe(true);
    });

    it('reports permission as not granted when no consent exists for the latest revision', async () => {
        const shopwareServicesStore = useShopwareServicesStore();
        shopwareServicesStore.config = {
            disabled: false,
            permissionsConsent: { revision: '2024-01-01' },
        };
        shopwareServicesStore.revisions = {
            'latest-revision': '2025-06-25',
            'available-revisions': [],
        };

        await expect(permissions.isPermissionGrantedFromSdk()).resolves.toBe(false);
    });

    it('loads the services context on demand when it is not present yet', async () => {
        Shopware.Service('shopwareServicesService').getServicesContext.mockResolvedValueOnce({ disabled: true });

        await expect(permissions.isPermissionGrantedFromSdk()).resolves.toBe(true);
        expect(Shopware.Service('shopwareServicesService').getServicesContext).toHaveBeenCalled();
    });
});
