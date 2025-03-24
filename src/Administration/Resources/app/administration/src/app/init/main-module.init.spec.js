/**
 * @sw-package framework
 */
import { ui } from '@shopware-ag/meteor-admin-sdk';
import initMainModules from 'src/app/init/main-module.init';

describe('src/app/init/main-module.init.ts', () => {
    beforeAll(() => {
        initMainModules();
    });

    beforeEach(() => {
        Shopware.Store.get('extensionSdkModules').modules = [];

        Shopware.Store.get('extensions').extensionsState = {};
        Shopware.Store.get('extensions').addExtension({
            name: 'jestapp',
            baseUrl: '',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });

        // Clear mocks
        jest.clearAllMocks();
    });

    it('should init the main module handler', async () => {
        await ui.mainModule.addMainModule({
            heading: 'My awesome module',
            locationId: 'my-awesome-module',
            displaySearchBar: true,
        });

        expect(Shopware.Store.get('extensionSdkModules').modules).toHaveLength(1);
        expect(Shopware.Store.get('extensionSdkModules').modules[0]).toEqual({
            id: expect.any(String),
            baseUrl: '',
            heading: 'My awesome module',
            displaySearchBar: true,
            locationId: 'my-awesome-module',
        });
    });

    it('should not handle requests when extension is not valid', async () => {
        Shopware.Store.get('extensions').extensionsState = {};

        await expect(async () => {
            await ui.mainModule.addMainModule({
                heading: 'My awesome module',
                locationId: 'my-awesome-module',
                displaySearchBar: true,
            });
        }).rejects.toThrow(new Error('Extension with the origin "" not found.'));

        expect(Shopware.Store.get('extensionSdkModules').modules).toHaveLength(0);
    });

    it('should not commit the extension when moduleID could not be generated', async () => {
        jest.spyOn(Shopware.Store.get('extensionSdkModules'), 'addModule').mockImplementationOnce(() => {
            return Promise.resolve(null);
        });

        await ui.mainModule.addMainModule({
            heading: 'My awesome module',
            locationId: 'my-awesome-module',
            displaySearchBar: true,
        });

        expect(Shopware.Store.get('extensionSdkModules').modules).toHaveLength(0);
    });

    it('should be able to update the hidden smart bars', async () => {
        await ui.mainModule.hideSmartBar({ locationId: 'my-awesome-module' });

        expect(Shopware.Store.get('extensionSdkModules').hiddenSmartBars).toEqual(['my-awesome-module']);
    });
});
