/* eslint-disable sw-test-rules/test-file-max-lines-warning */

/**
 * @sw-package framework
 */

import useModuleIconColors from 'src/app/composables/use-module-icon-colors';
import createWrapper from './sw-admin-menu-item.spec/create-wrapper';
import catalogues from './sw-admin-menu-item.spec/catalogues';

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
                    color: '#46954B',
                    path: 'sw.product.index',
                    icon: 'regular-products',
                    parent: 'sw-catalogue',
                    position: 10,
                    children: [],
                },
            },
        });

        expect(wrapper.find('.sw-admin-menu__sub-navigation-list').exists()).toBe(false);
        expect(wrapper.classes()).toContain('navigation-list-item__sw-product');
    });

    it('should show a link when a path is provided', async () => {
        const wrapper = await createWrapper({
            props: {
                entry: {
                    id: 'sw-product',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#46954B',
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

        // Entries without a usable path render a collapsible trigger button instead of a link
        const navigationLink = wrapper.find('.sw-admin-menu__navigation-link');
        expect(navigationLink.element.tagName).toBe('BUTTON');
    });

    it('should not show the menu entry when user has no privilege', async () => {
        const wrapper = await createWrapper({
            props: {
                entry: {
                    id: 'sw-product',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#46954B',
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
                    color: '#46954B',
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

        expect(wrapper.find('.navigation-list-item__sw-product .sw-admin-menu__navigation-link').exists()).toBe(true);
    });

    it('should check route access by exact route name', async () => {
        const wrapper = await createWrapper({
            privileges: [],
            props: {
                entry: {
                    id: 'sw-extension',
                    label: 'sw-extension.general.mainMenuItemGeneral',
                    color: '#0870FF',
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
                    color: '#46954B',
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
                            color: '#46954B',
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
                            color: '#46954B',
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

        // Entries without a usable path render a collapsible trigger button instead of a link
        const navigationLink = wrapper.find('.sw-admin-menu__navigation-link');
        expect(navigationLink.element.tagName).toBe('BUTTON');
    });

    it('should show a link when the path goes to a route which needs a privilege which is set', async () => {
        const wrapper = await createWrapper({
            privileges: ['product.viewer'],
            props: {
                entry: {
                    id: 'sw-product',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#46954B',
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
                            color: '#46954B',
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
                            color: '#46954B',
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
                    color: '#46954B',
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
                            color: '#46954B',
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
                            color: '#46954B',
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
                    color: '#46954B',
                    icon: 'regular-products',
                    parent: 'sw-catalogue',
                    position: 10,
                    moduleType: 'core',
                    level: 1,
                    children: [
                        {
                            id: 'sw-product',
                            label: 'sw-product.general.mainMenuItemGeneral',
                            color: '#46954B',
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
                            color: '#46954B',
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
                    color: '#46954B',
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
                            color: '#46954B',
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
                            color: '#46954B',
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
                    color: '#46954B',
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
                    color: '#46954B',
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
                    color: '#46954B',
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

    it('should not show the icon on sub menu items', async () => {
        const wrapper = await createWrapper({
            privileges: [],
            props: {
                entry: {
                    id: 'sw-product',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#46954B',
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
                            color: '#46954B',
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
                            color: '#46954B',
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

    describe('module icon colors', () => {
        const productEntry = {
            id: 'sw-product',
            label: 'sw-product.general.mainMenuItemGeneral',
            color: '#46954B',
            path: 'sw.product.index',
            icon: 'regular-products',
            position: 10,
            level: 1,
            moduleType: 'core',
            children: [],
        };

        afterEach(() => {
            useModuleIconColors().enabled.value = false;
        });

        it('should leave the icon color to the stylesheet by default', async () => {
            const wrapper = await createWrapper({ props: { entry: productEntry } });

            expect(wrapper.vm.navigationIconColor).toBeUndefined();
            expect(wrapper.find('.sw-admin-menu__navigation-link-icon').attributes('style')).not.toContain('color');
        });

        it('should paint the icon in the module color when the preference is enabled', async () => {
            useModuleIconColors().enabled.value = true;

            const wrapper = await createWrapper({ props: { entry: productEntry } });

            expect(wrapper.vm.navigationIconColor).toBe('#46954B');
            expect(wrapper.find('.sw-admin-menu__navigation-link-icon').attributes('style')).toContain(
                'color: rgb(70, 149, 75)',
            );
        });

        it('should not mark the row as module colored by default', async () => {
            const wrapper = await createWrapper({ props: { entry: productEntry } });

            expect(wrapper.classes()).not.toContain('is--module-colored');
        });

        it('should mark the row as module colored so the active state drops the brand tint', async () => {
            useModuleIconColors().enabled.value = true;

            const wrapper = await createWrapper({ props: { entry: productEntry } });

            expect(wrapper.classes()).toContain('is--module-colored');
        });

        it('should expose the module color to sub items as a custom property', async () => {
            useModuleIconColors().enabled.value = true;

            const wrapper = await createWrapper({ props: { entry: catalogues } });
            await flushPromises();

            expect(wrapper.attributes('style')).toContain('--sw-admin-menu-module-color: #46954B');
        });

        it('should not expose a module color while the preference is off', async () => {
            const wrapper = await createWrapper({ props: { entry: catalogues } });
            await flushPromises();

            expect(wrapper.attributes('style')).toBeUndefined();
        });

        it('should not mark rows without a module color', async () => {
            useModuleIconColors().enabled.value = true;

            const wrapper = await createWrapper({
                props: { entry: { ...productEntry, color: undefined } },
            });

            expect(wrapper.classes()).not.toContain('is--module-colored');
        });
    });

    it('should emit a branch toggle with the chevron following the open state', async () => {
        const wrapper = await createWrapper({
            props: {
                entry: catalogues,
            },
        });

        expect(wrapper.vm.expandIcon).toBe('regular-chevron-down-xs');

        await wrapper.find('.sw-admin-menu__navigation-link').trigger('click');

        expect(wrapper.emitted('branch-toggle')).toEqual([
            [{ entry: catalogues, open: true }],
        ]);
    });
});
