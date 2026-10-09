import { createPinia, setActivePinia } from 'pinia';
import * as permissions from './permissions';
import { useShopwareServicesStore } from '../store/shopware-services.store';

describe('src/module/sw-settings-services/composables/permissions', () => {
    let reloadMock;
    const serviceOrigin = 'https://copilot.staging-apps.shopware.io';

    const serviceRequest = () => ({
        _event_: new MessageEvent('message', { origin: serviceOrigin }),
    });

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
        Shopware.Store.get('extensions').extensionsState = {
            SwagCopilot: {
                name: 'SwagCopilot',
                baseUrl: serviceOrigin,
                permissions: {},
                type: 'app',
                sourceType: 'service',
            },
        };
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
                baseUrl: serviceOrigin,
                permissions: {},
                type: 'app',
                sourceType: 'service',
            },
        };

        await permissions.grantPermissionsFromSdk({}, serviceRequest());

        expect(Shopware.Service('shopwareServicesService').acceptRevision).toHaveBeenCalledWith('2025-06-25');
        expect(reloadMock).toHaveBeenCalled();
    });

    it('rejects SDK permission grants from non-Service origins', () => {
        Shopware.Store.get('extensions').extensionsState = {
            SomeService: {
                name: 'SomeService',
                baseUrl: 'https://api.example.company',
                permissions: {},
                type: 'app',
                sourceType: 'service',
            },
        };

        expect(() =>
            permissions.grantPermissionsFromSdk(
                {},
                { _event_: new MessageEvent('message', { origin: 'https://api.example.com' }) },
            ),
        ).toThrow('Only Shopware Services can access this handler.');
    });

    it('rejects SDK permission grants when a local extension shares the Service origin', () => {
        Shopware.Store.get('extensions').extensionsState = {
            SomeService: {
                name: 'SomeService',
                baseUrl: serviceOrigin,
                permissions: {},
                type: 'app',
                sourceType: 'service',
            },
            SomeLocalExtension: {
                name: 'SomeLocalExtension',
                baseUrl: serviceOrigin,
                permissions: {},
                type: 'app',
                sourceType: 'local',
            },
        };

        expect(() => permissions.grantPermissionsFromSdk({}, serviceRequest())).toThrow(
            'Only Shopware Services can access this handler.',
        );
    });

    it('rejects SDK permission status requests from non-Service origins', async () => {
        Shopware.Store.get('extensions').extensionsState = {
            SomeApp: {
                name: 'SomeApp',
                baseUrl: 'https://app.example.com',
                permissions: {},
                type: 'app',
                sourceType: null,
            },
        };

        await expect(permissions.isPermissionGrantedFromSdk({}, serviceRequest())).rejects.toThrow(
            'Only Shopware Services can access this handler.',
        );
    });

    it('reports permission as granted when Shopware Services are disabled', async () => {
        const shopwareServicesStore = useShopwareServicesStore();
        shopwareServicesStore.config = { disabled: true };

        await expect(permissions.isPermissionGrantedFromSdk({}, serviceRequest())).resolves.toBe(true);
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

        await expect(permissions.isPermissionGrantedFromSdk({}, serviceRequest())).resolves.toBe(true);
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

        await expect(permissions.isPermissionGrantedFromSdk({}, serviceRequest())).resolves.toBe(false);
    });

    it('loads the services context on demand when it is not present yet', async () => {
        Shopware.Service('shopwareServicesService').getServicesContext.mockResolvedValueOnce({ disabled: true });

        await expect(permissions.isPermissionGrantedFromSdk({}, serviceRequest())).resolves.toBe(true);
        expect(Shopware.Service('shopwareServicesService').getServicesContext).toHaveBeenCalled();
    });
});
