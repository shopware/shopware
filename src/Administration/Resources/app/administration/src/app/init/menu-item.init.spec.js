import initMenuItems from 'src/app/init/menu-item.init';
import { ui } from '@shopware-ag/meteor-admin-sdk';

let stateDispatchBackup = null;
describe('src/app/init/menu-item.init.ts', () => {
    beforeAll(() => {
        initMenuItems();
        stateDispatchBackup = Shopware.State.dispatch;
    });

    beforeEach(() => {
        Object.defineProperty(Shopware.State, 'dispatch', {
            value: stateDispatchBackup,
            writable: true,
            configurable: true,
        });
        Shopware.State.get('extensionSdkModules').modules = [];

        Shopware.State._store.state.extensions = {};
        Shopware.State.commit('extensions/addExtension', {
            name: 'jestapp',
            baseUrl: '',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });
    });

    it('should handle incoming menuItemAdd requests', async () => {
        await ui.menu.addMenuItem({
            label: 'Test item',
            locationId: 'your-location-id',
            displaySearchBar: true,
            displaySmartBar: true,
            parent: 'sw-catalogue',
        });

        expect(Shopware.State.get('extensionSdkModules').modules).toHaveLength(1);
    });

    it('should not handle requests when extension is not valid', async () => {
        Shopware.State._store.state.extensions = {};

        await expect(async () => {
            await ui.menu.addMenuItem({
                label: 'Test item',
                locationId: 'your-location-id',
                displaySearchBar: true,
                displaySmartBar: true,
                parent: 'sw-catalogue',
            });
        }).rejects.toThrow(new Error('Extension with the origin "" not found.'));

        expect(Shopware.State.get('extensionSdkModules').modules).toHaveLength(0);
    });

    it('should not commit the extension when moduleID could not be generated', async () => {
        jest.spyOn(Shopware.State, 'dispatch').mockImplementationOnce(() => {
            return Promise.resolve(null);
        });

        await ui.menu.addMenuItem({
            label: 'Test item',
            locationId: 'your-location-id',
            displaySearchBar: true,
            displaySmartBar: true,
            parent: 'sw-catalogue',
        });

        expect(Shopware.State.get('extensionSdkModules').modules).toHaveLength(0);
    });
});
