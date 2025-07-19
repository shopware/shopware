/**
 * @sw-package framework
 */

import type { smartBarButtonAdd } from '@shopware-ag/meteor-admin-sdk/es/ui/main-module/';
import type { ExtensionSdkModule } from './extension-sdk-module.store';

describe('extensionSdkModules.store', () => {
    let store = Shopware.Store.get('extensionSdkModules');

    beforeEach(() => {
        store = Shopware.Store.get('extensionSdkModules');
        store.modules = [];
        store.smartBarButtons = [];
        store.hiddenSmartBars = [];
    });

    it('has initial state', () => {
        expect(store.modules).toEqual([]);
        expect(store.smartBarButtons).toEqual([]);
        expect(store.hiddenSmartBars).toEqual([]);
    });

    it('addModule', async () => {
        const module = {
            heading: 'Test',
            locationId: 'test',
            displaySearchBar: true,
            displaySmartBar: true,
            displayLanguageSwitch: true,
            baseUrl: 'test',
        };

        await store.addModule(module);

        expect(store.modules).toEqual([
            {
                ...module,
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                id: expect.any(String),
            },
        ]);
    });

    it('addModule should not add the same module twice', async () => {
        const module = {
            heading: 'Test',
            locationId: 'test',
            displaySearchBar: true,
            displaySmartBar: true,
            displayLanguageSwitch: true,
            baseUrl: 'test',
        };

        await store.addModule(module);
        await store.addModule(module);

        expect(store.modules).toEqual([
            {
                ...module,
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                id: expect.any(String),
            },
        ]);
    });

    it('addSmartBarButton', () => {
        const button: Omit<smartBarButtonAdd, 'responseType'> = {
            label: 'Test',
            locationId: 'test',
            buttonId: 'test',
            disabled: false,
            variant: 'primary',
            onClickCallback: () => {},
        };

        store.addSmartBarButton(button);

        expect(store.smartBarButtons).toEqual([
            button,
        ]);
    });

    it('addHiddenSmartBar', () => {
        store.addHiddenSmartBar('test');

        expect(store.hiddenSmartBars).toEqual([
            'test',
        ]);
    });

    it('getRegisteredModuleInformation', () => {
        const module: ExtensionSdkModule = {
            id: 'test',
            heading: 'Test',
            locationId: 'test',
            displaySearchBar: true,
            displaySmartBar: true,
            displayLanguageSwitch: true,
            baseUrl: 'test',
        };

        store.modules.push(module);

        expect(store.getRegisteredModuleInformation('test')).toEqual([
            module,
        ]);
    });
});
