import { createPinia, setActivePinia } from 'pinia';
import { revokePermissions, grantPermissions } from './permissions';
import { useShopwareServicesStore } from '../store/shopware-services.store';

describe('src/module/sw-settings-services/composables/permissions', () => {
    let originalLocation;

    beforeAll(() => {
        Shopware.Service().register('shopwareServicesService', () => ({
            acceptRevision: jest.fn(),
            revokePermissions: jest.fn(),
        }));

        originalLocation = window.location;

        Object.defineProperty(window, 'location', { configurable: true, value: { reload: jest.fn() } });
    });

    beforeEach(() => {
        setActivePinia(createPinia());
        useShopwareServicesStore();
    });

    afterAll(() => {
        Object.defineProperty(window, 'location', { configurable: true, value: originalLocation });
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

        await grantPermissions();

        expect(Shopware.Service('shopwareServicesService').acceptRevision).toHaveBeenCalledWith('2025-06-25');
        expect(window.location.reload).toHaveBeenCalled();
    });

    it('throws exception if there is no current revision', async () => {
        await expect(() => grantPermissions()).rejects.toThrow(new Error('No revision available'));
    });

    it('calls shopware service to revoke permissions and reloads', async () => {
        await revokePermissions();

        expect(Shopware.Service('shopwareServicesService').revokePermissions).toHaveBeenCalled();
        expect(window.location.reload).toHaveBeenCalled();
    });
});
