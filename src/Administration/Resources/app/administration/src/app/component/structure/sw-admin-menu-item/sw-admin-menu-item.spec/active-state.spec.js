/**
 * @sw-package framework
 */

import createWrapper from './create-wrapper';

const extensionsEntry = () => ({
    id: 'sw-extension',
    label: 'sw-extension.mainMenu.mainMenuItemExtensionStore',
    icon: 'regular-plug',
    moduleType: 'core',
    position: 70,
    level: 1,
    children: [
        {
            id: 'sw-extension-store',
            path: 'sw.extension.store',
            label: 'sw-extension.mainMenu.store',
            parent: 'sw-extension',
            level: 2,
            children: [],
        },
        {
            id: 'sw-extension-my-extensions',
            path: 'sw.extension.my-extensions',
            label: 'sw-extension.mainMenu.purchased',
            parent: 'sw-extension',
            level: 2,
            children: [],
        },
    ],
});

const myExtensionsRoute = {
    name: 'sw.extension.my-extensions.listing.app',
    matched: [
        { name: 'sw.extension.my-extensions' },
        { name: 'sw.extension.my-extensions.listing' },
        { name: 'sw.extension.my-extensions.listing.app' },
    ],
};

describe('src/app/component/structure/sw-admin-menu-item: active state', () => {
    beforeEach(() => {
        Shopware.Store.get('settingsItems').settingsGroups.shop = [];
        Shopware.Store.get('settingsItems').settingsGroups.system = [];
        Shopware.Store.get('adminMenu').clearExpandedMenuEntries();
    });

    it('should not show the active state when another route is active', async () => {
        const wrapper = await createWrapper({
            privileges: [],
            route: {
                name: 'sw.bar.index',
                matched: [{ name: 'sw.bar.index' }],
            },
            props: {
                entry: {
                    path: 'sw.foo.index',
                    label: 'sw-foo.general.mainMenuItemList',
                    id: 'sw-foo',
                    moduleType: 'plugin',
                    parent: 'sw-catalogue',
                    position: 1010,
                    children: [],
                    level: 2,
                },
            },
        });

        await flushPromises();

        expect(wrapper.vm.rowActive).toBe(false);
    });

    it('should mark the "Extensions" parent active on a child route when the sidebar is collapsed', async () => {
        const wrapper = await createWrapper({
            route: myExtensionsRoute,
            props: {
                entry: extensionsEntry(),
                sidebarExpanded: false,
            },
        });

        await flushPromises();

        // Collapsed: the parent itself carries the active highlight.
        expect(wrapper.vm.rowActive).toBe(true);
    });

    it('should set the "Extensions" parent highlight to its active child when the branch is open', async () => {
        const wrapper = await createWrapper({
            route: myExtensionsRoute,
            props: {
                entry: extensionsEntry(),
                sidebarExpanded: true,
                isExpanded: true,
            },
        });

        await flushPromises();

        // Open branch: the child shows the "current page" highlight, the parent shows the child-active state.
        expect(wrapper.vm.rowActive).toBe(false);
        expect(wrapper.vm.childRouteActive).toBe(true);
    });

    it('should mark the active extension child item active', async () => {
        const wrapper = await createWrapper({
            route: myExtensionsRoute,
            props: {
                entry: {
                    id: 'sw-extension-my-extensions',
                    path: 'sw.extension.my-extensions',
                    label: 'sw-extension.mainMenu.purchased',
                    parent: 'sw-extension',
                    level: 2,
                    children: [],
                },
                menuDepth: 2,
            },
        });

        await flushPromises();

        expect(wrapper.vm.rowActive).toBe(true);
    });

    it('should keep a core module parent active on a detail page via the parentPath bridge', async () => {
        const wrapper = await createWrapper({
            route: {
                name: 'sw.product.detail.base',
                matched: [
                    { name: 'sw.product.detail' },
                    { name: 'sw.product.detail.base' },
                ],
                meta: { parentPath: 'sw.product.index' },
            },
            routerRoutes: [{ name: 'sw.product.index', meta: {} }],
            props: {
                entry: {
                    id: 'sw-catalogue',
                    label: 'global.sw-admin-menu.navigation.mainMenuItemCatalogue',
                    icon: 'regular-products',
                    level: 1,
                    children: [
                        {
                            id: 'sw-product',
                            path: 'sw.product.index',
                            label: 'sw-product.general.mainMenuItemGeneral',
                            parent: 'sw-catalogue',
                            level: 2,
                            children: [],
                        },
                    ],
                },
                sidebarExpanded: false,
            },
        });

        await flushPromises();

        expect(wrapper.vm.rowActive).toBe(true);
    });
});
