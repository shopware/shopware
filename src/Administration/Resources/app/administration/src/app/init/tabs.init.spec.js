import Vue from 'vue';
import initTabs from 'src/app/init/tabs.init';
import { ui } from '@shopware-ag/admin-extension-sdk';

describe('src/app/init/tabs.init', () => {
    beforeAll(() => {
        // Add mock router
        Shopware.Application.view.router = {
            resolve: () => Shopware.Application.view.router,
            route: {
                params: {},
            },
            options: {
                routes: {
                    find: () => {},
                },
            },
            replace: () => {},
            beforeEach: () => {},
            addRoutes: () => {},
            currentRoute: {
                fullPath: 'sw/foo-component-section-id',
                matched: [],
            },
        };
    });

    beforeEach(async () => {
        // Reset tab store
        Object.keys(Shopware.State.get('tabs').tabItems).forEach(key => {
            Vue.set(Shopware.State.get('tabs').tabItems, key, []);
        });
    });

    it('should initialize tab extension API correctly', async () => {
        // start handler for extensionAPI
        initTabs();

        // add tab
        await ui.tabs('foo-position-id').addTabItem({
            label: 'My tab item',
            componentSectionId: 'foo-component-section-id',
        });

        // Check if value was registered correctly
        expect(Shopware.State.get('tabs').tabItems).toHaveProperty('foo-position-id');
        expect(Shopware.State.get('tabs').tabItems['foo-position-id']).toEqual([{
            label: 'My tab item',
            componentSectionId: 'foo-component-section-id',
        }]);
    });
});
