/**
 * @sw-package framework
 */

import createWrapper from './create-wrapper';
import catalogues from './catalogues';

type MenuItemVm = { rowActive: boolean; childRouteActive: boolean; collapsibleOpen: boolean };

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

// Synthetic fixture in the shape menu.service builds for app modules:
// entries share one route name and differ only by their params.
const appModuleRoute = {
    name: 'sw.extension.module',
    matched: [{ name: 'sw.extension.module' }],
    params: { appName: 'PetShop', moduleName: 'cat-toys' },
};

const appModuleEntry = (withOwnRoute = false) => ({
    id: 'app-PetShop-main',
    label: 'Pet shop',
    level: 2,
    ...(withOwnRoute ? { path: 'sw.extension.module', params: { appName: 'PetShop', moduleName: 'pet-overview' } } : {}),
    children: [
        {
            id: 'app-PetShop-dog-food',
            path: 'sw.extension.module',
            params: { appName: 'PetShop', moduleName: 'dog-food' },
            label: 'Dog food',
            parent: 'app-PetShop-main',
            level: 3,
            children: [],
        },
        {
            id: 'app-PetShop-cat-toys',
            path: 'sw.extension.module',
            params: { appName: 'PetShop', moduleName: 'cat-toys' },
            label: 'Cat toys',
            parent: 'app-PetShop-main',
            level: 3,
            children: [],
        },
    ],
});

// A plugin route that is not itself a menu target and declares no parentPath: the menu entry
// is only reachable through the module manifest the router guard put on the route.
const pluginEntry = (id: string) => catalogues.children.find((child) => child.id === id);

const pluginDetailRoute = {
    name: 'sw.foo.detail',
    matched: [{ name: 'sw.foo.detail' }],
    params: { id: 'abc' },
    meta: { $module: { navigation: [{ path: 'sw.foo.index' }] } },
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

        expect((wrapper.vm as unknown as MenuItemVm).rowActive).toBe(false);
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
        expect((wrapper.vm as unknown as MenuItemVm).rowActive).toBe(true);
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
        expect((wrapper.vm as unknown as MenuItemVm).rowActive).toBe(false);
        expect((wrapper.vm as unknown as MenuItemVm).childRouteActive).toBe(true);
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

        expect((wrapper.vm as unknown as MenuItemVm).rowActive).toBe(true);
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

        expect((wrapper.vm as unknown as MenuItemVm).rowActive).toBe(true);
    });

    it('should keep a plugin entry active on its detail page without a declared parentPath', async () => {
        const wrapper = await createWrapper({
            route: pluginDetailRoute,
            props: {
                entry: pluginEntry('sw-foo'),
                menuDepth: 2,
            },
        });

        await flushPromises();

        expect((wrapper.vm as unknown as MenuItemVm).rowActive).toBe(true);
    });

    it('should mark the catalogue branch child-active on a plugin detail page', async () => {
        const wrapper = await createWrapper({
            route: pluginDetailRoute,
            props: {
                entry: catalogues,
                sidebarExpanded: true,
                isExpanded: true,
            },
        });

        await flushPromises();

        const vm = wrapper.vm as unknown as MenuItemVm;

        expect(vm.collapsibleOpen).toBe(true);
        expect(vm.rowActive).toBe(false);
        expect(vm.childRouteActive).toBe(true);
    });

    it('should mark the catalogue branch active on a plugin detail page when the sidebar is collapsed', async () => {
        const wrapper = await createWrapper({
            route: pluginDetailRoute,
            props: {
                entry: catalogues,
                sidebarExpanded: false,
            },
        });

        await flushPromises();

        expect((wrapper.vm as unknown as MenuItemVm).rowActive).toBe(true);
    });

    it('should not light a plugin entry from another module detail page', async () => {
        const wrapper = await createWrapper({
            route: pluginDetailRoute,
            props: {
                entry: pluginEntry('sw-bar'),
                menuDepth: 2,
            },
        });

        await flushPromises();

        expect((wrapper.vm as unknown as MenuItemVm).rowActive).toBe(false);
    });

    it('should hand the highlight to the active app child inside the collapsed flyout', async () => {
        const wrapper = await createWrapper({
            route: appModuleRoute,
            props: {
                entry: appModuleEntry(),
                menuDepth: 2,
                sidebarExpanded: false,
            },
        });

        await flushPromises();

        const vm = wrapper.vm as unknown as MenuItemVm;

        // The branch owning the active route opens inside the flyout and the child takes the highlight
        expect(vm.collapsibleOpen).toBe(true);
        expect(vm.rowActive).toBe(false);
        expect(vm.childRouteActive).toBe(true);
    });

    it('should open a linked app parent with its own route when a child route is active', async () => {
        const wrapper = await createWrapper({
            route: appModuleRoute,
            props: {
                entry: appModuleEntry(true),
                menuDepth: 2,
                sidebarExpanded: false,
            },
        });

        await flushPromises();

        const vm = wrapper.vm as unknown as MenuItemVm;

        expect(vm.collapsibleOpen).toBe(true);
        expect(vm.rowActive).toBe(false);
        expect(vm.childRouteActive).toBe(true);
    });
});
