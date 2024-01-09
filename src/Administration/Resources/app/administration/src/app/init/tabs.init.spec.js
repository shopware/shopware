import Vue from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import initTabs from 'src/app/init/tabs.init';
import { ui } from '@shopware-ag/admin-extension-sdk';

describe('src/app/init/tabs.init', () => {
    let routerMock;

    beforeAll(() => {
        global.allowedErrors = [
            ...global.allowedErrors,
            {
                msg: "[vue-router] Route with name 'sw.category.index.route-example-component-section-id' does not exist",
                method: 'warn',
            },
        ];

        // Mock component
        Shopware.Application.view.getComponent = () => ({});

        // Mock for router
        routerMock = createRouter({
            history: createWebHistory(),
            routes: [
                {
                    name: 'sw.category.index',
                    path: '/sw/category/index/:id',
                },
                {
                    name: 'sw.product.index',
                    path: '/sw/product/index/:id',
                },
            ],
        });
        routerMock.push('/sw/category/index/eXaMpLeId');
        Shopware.Application.view.router = routerMock;

        // start handler for extensionAPI
        initTabs();
    });

    beforeEach(async () => {
        // Reset tab store
        Object.keys(Shopware.State.get('tabs').tabItems).forEach(key => {
            Vue.set(Shopware.State.get('tabs').tabItems, key, []);
        });
    });

    it('should initialize tab extension API correctly', async () => {
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

    it('should create correct route entry for tab item when route gets opened (added via beforeEach middleware)', async () => {
        // add tab
        await ui.tabs('route-position-example-id').addTabItem({
            label: 'My tab item with route',
            componentSectionId: 'route-example-component-section-id',
        });

        // initialize view
        await Shopware.Application._resolveViewInitialized();

        // Visit the route and expect that the interceptor redirects the route
        await routerMock.push('/sw/category/index/eXaMpLeId/route-example-component-section-id');

        // Check if route was created correctly
        expect(
            routerMock.resolve('/sw/category/index/eXaMpLeId/route-example-component-section-id').matched[1],
        ).toEqual(expect.objectContaining({
            name: 'sw.category.index.route-example-component-section-id',
            path: '/sw/category/index/:id?/route-example-component-section-id',
        }));
    });
});
