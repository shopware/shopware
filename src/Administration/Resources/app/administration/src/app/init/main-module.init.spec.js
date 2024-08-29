/**
 * @package admin
 */
import { ui } from '@shopware-ag/meteor-admin-sdk';
import initMainModules from 'src/app/init/main-module.init';

let stateDispatchBackup = null;

describe('src/app/init/main-module.init.ts', () => {
    beforeAll(() => {
        initMainModules();
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

    it('should init the main module handler', async () => {
        await ui.mainModule.addMainModule({
            heading: 'My awesome module',
            locationId: 'my-awesome-module',
            displaySearchBar: true,
        });

        expect(Shopware.State.get('extensionSdkModules').modules).toHaveLength(1);
        expect(Shopware.State.get('extensionSdkModules').modules[0]).toEqual({
            id: expect.any(String),
            baseUrl: '',
            heading: 'My awesome module',
            displaySearchBar: true,
            locationId: 'my-awesome-module',
        });
    });

    it('should not handle requests when extension is not valid', async () => {
        Shopware.State._store.state.extensions = {};

        await expect(async () => {
            await ui.mainModule.addMainModule({
                heading: 'My awesome module',
                locationId: 'my-awesome-module',
                displaySearchBar: true,
            });
        }).rejects.toThrow(new Error('Extension with the origin "" not found.'));

        expect(Shopware.State.get('extensionSdkModules').modules).toHaveLength(0);
    });

    it('should not commit the extension when moduleID could not be generated', async () => {
        jest.spyOn(Shopware.State, 'dispatch').mockImplementationOnce(() => {
            return Promise.resolve(null);
        });

        await ui.mainModule.addMainModule({
            heading: 'My awesome module',
            locationId: 'my-awesome-module',
            displaySearchBar: true,
        });

        expect(Shopware.State.get('extensionSdkModules').modules).toHaveLength(0);
    });
});
