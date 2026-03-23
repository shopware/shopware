import { createPinia, setActivePinia } from 'pinia';
import * as permissions from './permissions';
import { useShopwareServicesStore } from '../store/shopware-services.store';

describe('src/module/sw-settings-services/composables/permissions', () => {
    let reloadMock;

    beforeAll(() => {
        Shopware.Service().register('shopwareServicesService', () => ({
            acceptRevision: jest.fn(),
            revokePermissions: jest.fn(),
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
});
