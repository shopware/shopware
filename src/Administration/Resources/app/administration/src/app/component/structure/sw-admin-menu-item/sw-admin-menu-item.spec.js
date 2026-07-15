/* eslint-disable sw-test-rules/test-file-max-lines-warning */

/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import AclService from 'src/app/service/acl.service';
import 'src/app/component/structure/sw-admin-menu-item';
import catalogues from './_sw-admin-menu-item/catalogues';

async function createWrapper({ props = {}, privileges = [], route = {}, routerRoutes = null } = {}) {
    const collectRoutes = (entry) => {
        if (!entry) {
            return [];
        }

        return [
            ...(entry.children ?? []).flatMap((child) => collectRoutes(child)),
            entry,
        ];
    };

    const $router = {
        getRoutes: () =>
            routerRoutes ??
            collectRoutes(props.entry)
                .filter((entry) => entry.path)
                .map((entry) => ({
                    name: entry.path,
                    meta: {
                        privilege: entry.privilege,
                    },
                })),
    };

    const can = (privilege) => {
        if (!privilege) {
            return true;
        }

        return privileges.includes(privilege);
    };

    const aclService = new AclService();

    return mount(await wrapTestComponent('sw-admin-menu-item', { sync: true }), {
        props,
        global: {
            stubs: {
                'sw-admin-menu-item': await Shopware.Component.build('sw-admin-menu-item'),
                'router-link': {
                    template: '<a class="router-link"></a>',
                    props: ['to'],
                },
            },
            mocks: {
                $route: {
                    name: route.name,
                    params: route.params ?? {},
                    matched: route.matched ?? [],
                    meta: { $module: { name: '' }, ...(route.meta ?? {}) },
                },
                $router,
            },
            provide: {
                acl: {
                    can,
                    hasAccessToRoute: (path) => {
                        const match = $router.getRoutes().find((route) => route.name === path);

                        if (!match?.meta) {
                            return true;
                        }

                        return can(match.meta.privilege);
                    },
                    hasActiveSettingModules: aclService.hasActiveSettingModules,
                    state: aclService.state,
                },
                feature: {},
            },
        },
    });
}

describe('src/app/component/structure/sw-admin-menu-item', () => {
    beforeEach(async () => {
        Shopware.Store.get('settingsItems').settingsGroups.shop = [];
        Shopware.Store.get('settingsItems').settingsGroups.system = [];
    });

    it('should contain all menu entries', async () => {
        const wrapper = await createWrapper({
            props: {
                entry: catalogues,
            },
        });

        const children = wrapper.findAll('.sw-admin-menu__sub-navigation-list .sw-admin-menu__navigation-list-item');
        expect(children).toHaveLength(8);

        expect(wrapper.classes()).toContain('navigation-list-item__sw-catalogue');
        expect(children.at(0).classes()).toContain('navigation-list-item__sw-product');
        expect(children.at(1).classes()).toContain('navigation-list-item__sw-review');
        expect(children.at(2).classes()).toContain('navigation-list-item__sw-category');
        expect(children.at(3).classes()).toContain('navigation-list-item__sw-product-stream');
        expect(children.at(4).classes()).toContain('navigation-list-item__sw-property');
        expect(children.at(5).classes()).toContain('navigation-list-item__sw-manufacturer');

        expect(wrapper.vm).toBeTruthy();
    });

    it('should show only one entry without children', async () => {
        const wrapper = await createWrapper({
            props: {
                entry: {
                    id: 'sw-product',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    path: 'sw.product.index',
                    icon: 'regular-products',
                    parent: 'sw-catalogue',
                    position: 10,
                    children: [],
                },
            },
        });

        const children = wrapper.findAll('sw-admin-menu-item-stub');
        expect(children).toHaveLength(0);

        expect(wrapper.classes()).toContain('navigation-list-item__sw-product');
    });

    it('should show a link when a path is provided', async () => {
        const wrapper = await createWrapper({
            props: {
                entry: {
                    id: 'sw-product',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    path: 'sw.product.index',
                    icon: 'regular-products',
                    parent: 'sw-catalogue',
                    position: 10,
                    level: 1,
                    moduleType: 'core',
                    children: [],
                },
            },
        });

        const routerLink = wrapper.findComponent('.navigation-list-item__sw-product .router-link');

        expect(routerLink.props().to).toMatchObject({
            name: 'sw.product.index',
        });
    });

    it('should not show a link when no path is provided', async () => {
        const wrapper = await createWrapper({
            props: {
                entry: catalogues,
            },
        });

        const navigationLink = wrapper.find('.sw-admin-menu__navigation-link');
        expect(navigationLink.element.tagName).toBe('SPAN');
    });

    it('should not show the menu entry when user has no privilege', async () => {
        const wrapper = await createWrapper({
            props: {
                entry: {
                    id: 'sw-product',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    path: 'sw.product.index',
                    icon: 'regular-products',
                    parent: 'sw-catalogue',
                    privilege: 'product.viewer',
                    position: 10,
                    moduleType: 'core',
                    level: 1,
                    children: [],
                },
            },
        });

        expect(wrapper.html()).toMatchInlineSnapshot('"<!--v-if-->"');
    });

    it('should show the menu entry when user has the privilege', async () => {
        const wrapper = await createWrapper({
            privileges: ['product.viewer'],
            props: {
                entry: {
                    id: 'sw-product',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    path: 'sw.product.index',
                    icon: 'regular-products',
                    parent: 'sw-catalogue',
                    privilege: 'product.viewer',
                    position: 10,
                    moduleType: 'core',
                    level: 1,
                    children: [],
                },
            },
        });

        expect(wrapper.html().length).toBeGreaterThan(1);
    });

    it('should check route access by exact route name', async () => {
        const wrapper = await createWrapper({
            privileges: [],
            props: {
                entry: {
                    id: 'sw-extension',
                    label: 'sw-extension.general.mainMenuItemGeneral',
                    color: '#189EFF',
                    path: 'sw.extension.my-extensions',
                    icon: 'regular-plug',
                    parent: null,
                    privilege: 'system.plugin_maintain',
                    position: 10,
                    moduleType: 'core',
                    level: 1,
                    children: [],
                },
            },
        });

        expect(wrapper.vm.hasAccessToRoute('sw.extension.my-extensions')).toBe(false);
        expect(wrapper.vm.hasAccessToRoute('sw.extension.my.extensions')).toBe(true);
    });

    it('should not show a link when the path goes to a route which needs a privilege which is not set', async () => {
        const wrapper = await createWrapper({
            privileges: [],
            props: {
                entry: {
                    id: 'sw-product',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    path: 'sw.product.index',
                    icon: 'regular-products',
                    parent: 'sw-catalogue',
                    position: 10,
                    level: 1,
                    moduleType: 'core',
                    children: [
                        {
                            id: 'sw-product',
                            label: 'sw-product.general.mainMenuItemGeneral',
                            color: '#57D9A3',
                            path: 'sw.product.index',
                            icon: 'regular-products',
                            parent: 'sw-catalogue',
                            position: 10,
                            level: 2,
                            moduleType: 'core',
                            privilege: 'product.viewer',
                            children: [],
                        },
                        {
                            id: 'sw-review',
                            label: 'sw-review.general.mainMenuItemList',
                            color: '#57D9A3',
                            path: 'sw.review.index',
                            icon: 'regular-products',
                            parent: 'sw-catalogue',
                            position: 20,
                            level: 2,
                            moduleType: 'core',
                            children: [],
                        },
                    ],
                },
            },
        });

        const navigationLink = wrapper.find('.sw-admin-menu__navigation-link');
        expect(navigationLink.element.tagName).toBe('SPAN');
    });

    it('should show a link when the path goes to a route which needs a privilege which is set', async () => {
        const wrapper = await createWrapper({
            privileges: ['product.viewer'],
            props: {
                entry: {
                    id: 'sw-product',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    path: 'sw.product.index',
                    icon: 'regular-products',
                    parent: 'sw-catalogue',
                    position: 10,
                    level: 1,
                    moduleType: 'core',
                    children: [
                        {
                            id: 'sw-product',
                            label: 'sw-product.general.mainMenuItemGeneral',
                            color: '#57D9A3',
                            path: 'sw.product.index',
                            icon: 'regular-products',
                            parent: 'sw-catalogue',
                            position: 10,
                            level: 2,
                            moduleType: 'core',
                            meta: {
                                privilege: 'product.viewer',
                            },
                            children: [],
                        },
                        {
                            id: 'sw-review',
                            label: 'sw-review.general.mainMenuItemList',
                            color: '#57D9A3',
                            path: 'sw.review.index',
                            icon: 'regular-products',
                            parent: 'sw-catalogue',
                            position: 20,
                            level: 2,
                            moduleType: 'core',
                            children: [],
                        },
                    ],
                },
            },
        });

        const navigationLink = wrapper.findComponent('.sw-admin-menu__navigation-link');
        expect(navigationLink.element.tagName).toBe('A');

        expect(navigationLink.props().to).toMatchObject({
            name: 'sw.product.index',
        });
    });

    it('should not show the menu entry when all children have privileges the user do not have and the main path is also restricted', async () => {
        const wrapper = await createWrapper({
            privileges: [],
            props: {
                entry: {
                    id: 'sw-product',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    path: 'sw.product.index',
                    icon: 'regular-products',
                    parent: 'sw-catalogue',
                    position: 10,
                    level: 1,
                    moduleType: 'core',
                    children: [
                        {
                            id: 'sw-product',
                            label: 'sw-product.general.mainMenuItemGeneral',
                            color: '#57D9A3',
                            path: 'sw.product.index',
                            icon: 'regular-products',
                            parent: 'sw-catalogue',
                            position: 10,
                            level: 2,
                            moduleType: 'core',
                            privilege: 'product.viewer',
                            children: [],
                        },
                        {
                            id: 'sw-review',
                            label: 'sw-review.general.mainMenuItemList',
                            color: '#57D9A3',
                            path: 'sw.review.index',
                            icon: 'regular-products',
                            parent: 'sw-catalogue',
                            privilege: 'reviewer.viewer',
                            position: 20,
                            level: 2,
                            moduleType: 'core',
                            children: [],
                        },
                    ],
                },
            },
        });

        expect(wrapper.html()).toBe('<!--v-if-->');
    });

    it('should not show the menu entry when all children have privileges the user do not have', async () => {
        const wrapper = await createWrapper({
            privileges: [],
            props: {
                entry: {
                    id: 'sw-product',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    icon: 'regular-products',
                    parent: 'sw-catalogue',
                    position: 10,
                    moduleType: 'core',
                    level: 1,
                    children: [
                        {
                            id: 'sw-product',
                            label: 'sw-product.general.mainMenuItemGeneral',
                            color: '#57D9A3',
                            path: 'sw.product.index',
                            icon: 'regular-products',
                            parent: 'sw-catalogue',
                            position: 10,
                            level: 2,
                            moduleType: 'core',
                            privilege: 'product.viewer',
                            children: [],
                        },
                        {
                            id: 'sw-review',
                            label: 'sw-review.general.mainMenuItemList',
                            color: '#57D9A3',
                            path: 'sw.review.index',
                            icon: 'regular-products',
                            parent: 'sw-catalogue',
                            privilege: 'reviewer.viewer',
                            position: 20,
                            level: 2,
                            moduleType: 'core',
                            children: [],
                        },
                    ],
                },
            },
        });

        expect(wrapper.html()).toMatchInlineSnapshot('"<!--v-if-->"');
    });

    it('should show the menu entry when all children have privileges the user do not have but the main path is allowed', async () => {
        const wrapper = await createWrapper({
            privileges: [],
            props: {
                entry: {
                    id: 'sw-product',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    path: 'sw.cms.index',
                    icon: 'regular-products',
                    parent: 'sw-catalogue',
                    position: 10,
                    moduleType: 'core',
                    level: 1,
                    children: [
                        {
                            id: 'sw-product',
                            label: 'sw-product.general.mainMenuItemGeneral',
                            color: '#57D9A3',
                            path: 'sw.product.index',
                            icon: 'regular-products',
                            parent: 'sw-catalogue',
                            position: 10,
                            level: 2,
                            moduleType: 'core',
                            privilege: 'product.viewer',
                            children: [],
                        },
                        {
                            id: 'sw-review',
                            label: 'sw-review.general.mainMenuItemList',
                            color: '#57D9A3',
                            path: 'sw.review.index',
                            icon: 'regular-products',
                            parent: 'sw-catalogue',
                            privilege: 'reviewer.viewer',
                            position: 20,
                            level: 2,
                            moduleType: 'core',
                            children: [],
                        },
                    ],
                },
            },
        });

        const navigationLink = wrapper.findComponent('.sw-admin-menu__navigation-link');
        expect(navigationLink.element.tagName).toBe('A');
        expect(navigationLink.props().to).toMatchObject({
            name: 'sw.cms.index',
        });
    });

    it('should hide settings menu if no item is visible', async () => {
        Shopware.Store.get('settingsItems').settingsGroups.shop = [
            { privilege: 'no-set', path: 'it' },
        ];

        const wrapper = await createWrapper({
            privileges: [],
            props: {
                entry: {
                    id: 'sw-settings.index',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    path: 'sw.settings.index',
                    icon: 'regular-products',
                    level: 1,
                    moduleType: 'core',
                    position: 10,
                },
            },
        });

        expect(wrapper.html()).toMatchInlineSnapshot('"<!--v-if-->"');
    });

    it('settings should be shown if all item is visible', async () => {
        Shopware.Store.get('settingsItems').settingsGroups.shop = [
            { privilege: 'priv-1' },
            { privilege: 'priv-2' },
        ];

        const wrapper = await createWrapper({
            privileges: [
                'priv-1',
                'priv2',
            ],
            props: {
                entry: {
                    id: 'sw-settings.index',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    path: 'sw.settings.index',
                    icon: 'regular-products',
                    position: 10,
                    level: 1,
                    moduleType: 'core',
                    children: [],
                },
            },
        });

        expect(wrapper.html()).not.toBe('');
    });

    it('settings should be shown if one item is visible', async () => {
        Shopware.Store.get('settingsItems').settingsGroups.shop = [
            { privilege: 'priv-1' },
            { privilege: 'priv-2' },
        ];

        const wrapper = await createWrapper({
            privileges: ['priv-1'],
            props: {
                entry: {
                    id: 'sw-settings.index',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    path: 'sw.settings.index',
                    icon: 'regular-products',
                    position: 10,
                    level: 1,
                    moduleType: 'core',
                    children: [],
                },
            },
        });

        expect(wrapper.html()).not.toBe('');
    });

    it('should match route', async () => {
        const entries = [...catalogues.children];
        entries.unshift({
            id: 'sw-catalogue',
            moduleType: 'core',
            label: 'global.sw-admin-menu.navigation.mainMenuItemCatalogue',
            color: '#57D9A3',
            icon: 'regular-products',
            position: 20,
            level: 1,
        });

        Shopware.Store.get('adminMenu').adminModuleNavigation = entries;

        const wrapper = await createWrapper({
            privileges: [],
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

    // Landing on a deeply-nested extension route (no parentPath, no matching nav path → $current is null).
    const myExtensionsRoute = {
        name: 'sw.extension.my-extensions.listing.app',
        matched: [
            { name: 'sw.extension.my-extensions' },
            { name: 'sw.extension.my-extensions.listing' },
            { name: 'sw.extension.my-extensions.listing.app' },
        ],
    };

    it('should mark the path-less "Extensions" parent active on a child route when the sidebar is collapsed', async () => {
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

    it('should defer the "Extensions" parent highlight to its active child when the branch is open', async () => {
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

    it('should not mark "Extensions" active on an unrelated route', async () => {
        const wrapper = await createWrapper({
            route: { name: 'sw.order.index', matched: [{ name: 'sw.order.index' }] },
            props: {
                entry: extensionsEntry(),
                sidebarExpanded: true,
                isExpanded: true,
            },
        });

        await flushPromises();

        expect(wrapper.vm.rowActive).toBe(false);
        expect(wrapper.vm.childRouteActive).toBe(false);
    });

    it('should keep a core module parent active on a detail page via the parentPath bridge', async () => {
        // sw.product.detail is a *sibling* of the nav route sw.product.index (not in `matched`);
        // it bridges back through meta.parentPath. Verifies the full refactor did not regress this.
        const wrapper = await createWrapper({
            route: {
                name: 'sw.product.detail.base',
                matched: [{ name: 'sw.product.detail' }, { name: 'sw.product.detail.base' }],
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

    it('should not show the icon on sub menu items', async () => {
        const wrapper = await createWrapper({
            privileges: [],
            props: {
                entry: {
                    id: 'sw-product',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    path: 'sw.product.index',
                    icon: 'regular-products',
                    parent: 'sw-catalogue',
                    position: 10,
                    level: 1,
                    moduleType: 'core',
                    children: [
                        {
                            id: 'sw-product',
                            label: 'sw-product.general.mainMenuItemGeneral',
                            color: '#57D9A3',
                            path: 'sw.product.index',
                            icon: 'regular-products',
                            parent: 'sw-catalogue',
                            position: 10,
                            level: 2,
                            moduleType: 'core',
                            children: [],
                        },
                        {
                            id: 'sw-review',
                            label: 'sw-review.general.mainMenuItemList',
                            color: '#57D9A3',
                            path: 'sw.review.index',
                            icon: 'regular-products',
                            parent: 'sw-catalogue',
                            position: 20,
                            level: 2,
                            moduleType: 'core',
                            children: [],
                        },
                    ],
                },
            },
        });

        await flushPromises();

        const childMenuItem = wrapper.findComponent(
            '.sw-admin-menu__sub-navigation-list .sw-admin-menu__navigation-list-item',
        );
        expect(childMenuItem.props().displayIcon).toBe(false);
    });
});
