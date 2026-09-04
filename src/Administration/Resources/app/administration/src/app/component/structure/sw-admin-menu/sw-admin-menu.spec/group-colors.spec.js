/**
 * @sw-package framework
 */

import { config } from '@vue/test-utils';
import createWrapper, { registerAdminModules } from './create-wrapper';

function findEntry(entries, id) {
    for (const entry of entries) {
        if (entry.id === id) {
            return entry;
        }

        const match = findEntry(entry.children ?? [], id);

        if (match) {
            return match;
        }
    }

    return undefined;
}

describe('src/app/component/structure/sw-admin-menu - group colors', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.Store.get('session').currentLocale = 'en-GB';
        Shopware.Context.app.fallbackLocale = 'en-GB';
    });

    beforeEach(async () => {
        config.global.stubs = {
            ...config.global.stubs,
            transition: false,
        };

        jest.spyOn(Shopware.Utils.debug, 'error').mockImplementation(() => true);

        Shopware.Store.get('session').setCurrentUser(null);
        Shopware.Store.get('settingsItems').settingsGroups.shop = [];
        Shopware.Store.get('settingsItems').settingsGroups.system = [];
        Shopware.Store.get('shopwareApps').apps = [];

        registerAdminModules();

        Shopware.Module.register('sw-colored-group', {
            type: 'core',
            name: 'coloredGroup',
            title: 'Colored group',
            routes: { index: { component: 'sw-index', path: 'index' } },
            navigation: [
                {
                    id: 'sw-colored-group',
                    label: 'Colored group',
                    color: 'green',
                },
                {
                    id: 'sw-colored-child',
                    label: 'Colored child',
                    parent: 'sw-colored-group',
                    path: 'sw.colored.child.index',
                },
                {
                    id: 'sw-colored-grand-child',
                    label: 'Colored grand child',
                    parent: 'sw-colored-child',
                    path: 'sw.colored.grand.child.index',
                },
            ],
        });

        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should give every entry of a group the color of its first-level entry', () => {
        const entries = wrapper.vm.mainMenuEntries;

        expect(findEntry(entries, 'sw-colored-group').color).toBe('var(--sw-module-color-green)');
        expect(findEntry(entries, 'sw-colored-child').color).toBe('var(--sw-module-color-green)');
        expect(findEntry(entries, 'sw-colored-grand-child').color).toBe('var(--sw-module-color-green)');
    });

    it('should leave the entries of a group without a color uncolored', () => {
        const entries = wrapper.vm.mainMenuEntries;

        expect(findEntry(entries, 'sw.second.top.level').color).toBeUndefined();
        expect(findEntry(entries, 'sw.second.level.first').color).toBeUndefined();
    });
});
