import { createPinia, setActivePinia } from 'pinia';
import { revokePermissions, grantPermissions } from './permissions';
import { useShopwareServicesStore } from '../store/shopware-services.store';
import { reloadPage } from 'src/core/helper/navigation.helper';

jest.mock('src/core/helper/navigation.helper', () => ({
    reloadPage: jest.fn(),
}));

describe('src/module/sw-settings-services/composables/permissions', () => {
    beforeAll(() => {
        Shopware.Service().register('shopwareServicesService', () => ({
            acceptRevision: jest.fn(),
            revokePermissions: jest.fn(),
        }));
    });

    beforeEach(() => {
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

        await grantPermissions();

        expect(Shopware.Service('shopwareServicesService').acceptRevision).toHaveBeenCalledWith('2025-06-25');
        expect(reloadPage).toHaveBeenCalled();
    });

    it('throws exception if there is no current revision', async () => {
        await expect(() => grantPermissions()).rejects.toThrow(new Error('No revision available'));
    });

    it('calls shopware service to revoke permissions and reloads', async () => {
        await revokePermissions();

        expect(Shopware.Service('shopwareServicesService').revokePermissions).toHaveBeenCalled();
        expect(reloadPage).toHaveBeenCalled();
    });
});
