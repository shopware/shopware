import { shallowMount } from '@vue/test-utils';
import AclService from 'src/app/service/acl.service';
import 'src/app/component/structure/sw-admin-menu-item';
import catalogues from './_sw-admin-menu-item/catalogues';

function createWrapper({ propsData = {}, privileges = [] } = {}) {
    const $router = {
        match: (route) => {
            let match = propsData.entry;

            const path = route.replace(/\//g, '.');

            const matchedChild = propsData.entry.children.find(child => {
                return child.path === path;
            });

            if (matchedChild) {
                match = matchedChild;
            }

            return {
                ...match,
                privilege: undefined,
                meta: {
                    privilege: match.privilege
                }
            };
        }
    };

    const can = (privilege) => {
        if (!privilege) {
            return true;
        }

        return privileges.includes(privilege);
    };

    const aclService = new AclService(Shopware.State);

    return shallowMount(Shopware.Component.build('sw-admin-menu-item'), {
        sync: false,
        propsData: propsData,
        stubs: {
            'sw-icon': true,
            'sw-admin-menu-item': Shopware.Component.build('sw-admin-menu-item'),
            'router-link': {
                template: '<a class="router-link"></a>',
                props: ['to']
            }
        },
        mocks: {
            $tc: key => key,
            $route: {
                meta: {}
            },
            $router
        },
        provide: {
            acl: {
                can,
                hasAccessToRoute: (path) => {
                    const route = path.replace(/\./g, '/');
                    const match = $router.match(route);

                    if (!match.meta) {
                        return true;
                    }

                    return can(match.meta.privilege);
                },
                hasActiveSettingModules: aclService.hasActiveSettingModules,
                state: aclService.state
            }
        }
    });
}


describe('src/app/component/structure/sw-admin-menu-item', () => {
    beforeAll(() => {
        Shopware.Feature.isActive = () => true;
        Shopware.Service().register('feature', () => {
            return {
                isActive: () => true
            };
        });

        Shopware.State.registerModule('settingsItems', {
            namespaced: true,
            state: {
                settingsGroups: {
                    shop: [],
                    system: []
                }
            }
        });
    });

    beforeEach(() => {
        Shopware.State.get('settingsItems').settingsGroups.shop = [];
        Shopware.State.get('settingsItems').settingsGroups.system = [];
    });

    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper({
            propsData: {
                entry: catalogues
            }
        });

        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain all menu entries', async () => {
        const wrapper = createWrapper({
            propsData: {
                entry: catalogues
            }
        });

        const children = wrapper.findAll('.sw-admin-menu__sub-navigation-list .sw-admin-menu__navigation-list-item');
        expect(children.length).toBe(8);

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
        const wrapper = createWrapper({
            propsData: {
                entry: {
                    id: 'sw-product',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    path: 'sw.product.index',
                    icon: 'default-symbol-products',
                    parent: 'sw-catalogue',
                    position: 10,
                    children: []
                }
            }
        });

        const children = wrapper.findAll('sw-admin-menu-item-stub');
        expect(children.length).toBe(0);

        expect(wrapper.classes()).toContain('navigation-list-item__sw-product');
    });

    it('should show a link when a path is provided', async () => {
        const wrapper = createWrapper({
            propsData: {
                entry: {
                    id: 'sw-product',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    path: 'sw.product.index',
                    icon: 'default-symbol-products',
                    parent: 'sw-catalogue',
                    position: 10,
                    level: 1,
                    moduleType: 'core',
                    children: []
                }
            }
        });

        const routerLink = wrapper.find('.navigation-list-item__sw-product .router-link');

        expect(routerLink.props().to).toMatchObject({
            name: 'sw.product.index'
        });
    });

    it('should not show a link when no path is provided', async () => {
        const wrapper = createWrapper({
            propsData: {
                entry: catalogues
            }
        });

        const navigationLink = wrapper.find('.sw-admin-menu__navigation-link');
        expect(navigationLink.element.tagName).toBe('SPAN');
    });

    it('should not show the menu entry when user has no privilege', async () => {
        const wrapper = createWrapper({
            propsData: {
                entry: {
                    id: 'sw-product',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    path: 'sw.product.index',
                    icon: 'default-symbol-products',
                    parent: 'sw-catalogue',
                    privilege: 'product.viewer',
                    position: 10,
                    moduleType: 'core',
                    level: 1,
                    children: []
                }
            }
        });

        expect(wrapper.html()).toBe('');
    });

    it('should show the menu entry when user has the privilege', async () => {
        const wrapper = createWrapper({
            privileges: ['product.viewer'],
            propsData: {
                entry: {
                    id: 'sw-product',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    path: 'sw.product.index',
                    icon: 'default-symbol-products',
                    parent: 'sw-catalogue',
                    privilege: 'product.viewer',
                    position: 10,
                    moduleType: 'core',
                    level: 1,
                    children: []
                }
            }
        });

        expect(wrapper.html().length).toBeGreaterThan(1);
    });

    it('should not show a link when the path goes to a route which needs a privilege which is not set', async () => {
        const wrapper = createWrapper({
            privileges: [],
            propsData: {
                entry: {
                    id: 'sw-product',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    path: 'sw.product.index',
                    icon: 'default-symbol-products',
                    parent: 'sw-catalogue',
                    position: 10,
                    level: 1,
                    moduleType: 'core',
                    children: [{
                        id: 'sw-product',
                        label: 'sw-product.general.mainMenuItemGeneral',
                        color: '#57D9A3',
                        path: 'sw.product.index',
                        icon: 'default-symbol-products',
                        parent: 'sw-catalogue',
                        position: 10,
                        level: 2,
                        moduleType: 'core',
                        privilege: 'product.viewer',
                        children: []
                    }, {
                        id: 'sw-review',
                        label: 'sw-review.general.mainMenuItemList',
                        color: '#57D9A3',
                        path: 'sw.review.index',
                        icon: 'default-symbol-products',
                        parent: 'sw-catalogue',
                        position: 20,
                        level: 2,
                        moduleType: 'core',
                        children: []
                    }]
                }
            }
        });

        const navigationLink = wrapper.find('.sw-admin-menu__navigation-link');
        expect(navigationLink.element.tagName).toBe('SPAN');
    });

    it('should show a link when the path goes to a route which needs a privilege which is set', async () => {
        const wrapper = createWrapper({
            privileges: ['product.viewer'],
            propsData: {
                entry: {
                    id: 'sw-product',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    path: 'sw.product.index',
                    icon: 'default-symbol-products',
                    parent: 'sw-catalogue',
                    position: 10,
                    level: 1,
                    moduleType: 'core',
                    children: [{
                        id: 'sw-product',
                        label: 'sw-product.general.mainMenuItemGeneral',
                        color: '#57D9A3',
                        path: 'sw.product.index',
                        icon: 'default-symbol-products',
                        parent: 'sw-catalogue',
                        position: 10,
                        level: 2,
                        moduleType: 'core',
                        meta: {
                            privilege: 'product.viewer'
                        },
                        children: []
                    }, {
                        id: 'sw-review',
                        label: 'sw-review.general.mainMenuItemList',
                        color: '#57D9A3',
                        path: 'sw.review.index',
                        icon: 'default-symbol-products',
                        parent: 'sw-catalogue',
                        position: 20,
                        level: 2,
                        moduleType: 'core',
                        children: []
                    }]
                }
            }
        });

        const navigationLink = wrapper.find('.sw-admin-menu__navigation-link');
        expect(navigationLink.element.tagName).not.toBe('SPAN');
        expect(navigationLink.element.tagName).toBe('A');

        expect(navigationLink.props().to).toMatchObject({
            name: 'sw.product.index'
        });
    });

    // eslint-disable-next-line max-len
    it('should not show the menu entry when all children have privileges the user do not have and the main path is also restricted', async () => {
        const wrapper = createWrapper({
            privileges: [],
            propsData: {
                entry: {
                    id: 'sw-product',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    path: 'sw.product.index',
                    icon: 'default-symbol-products',
                    parent: 'sw-catalogue',
                    position: 10,
                    level: 1,
                    moduleType: 'core',
                    children: [{
                        id: 'sw-product',
                        label: 'sw-product.general.mainMenuItemGeneral',
                        color: '#57D9A3',
                        path: 'sw.product.index',
                        icon: 'default-symbol-products',
                        parent: 'sw-catalogue',
                        position: 10,
                        level: 2,
                        moduleType: 'core',
                        privilege: 'product.viewer',
                        children: []
                    }, {
                        id: 'sw-review',
                        label: 'sw-review.general.mainMenuItemList',
                        color: '#57D9A3',
                        path: 'sw.review.index',
                        icon: 'default-symbol-products',
                        parent: 'sw-catalogue',
                        privilege: 'reviewer.viewer',
                        position: 20,
                        level: 2,
                        moduleType: 'core',
                        children: []
                    }]
                }
            }
        });

        expect(wrapper.html()).toBe('');
    });

    it('should not show the menu entry when all children have privileges the user do not have', async () => {
        const wrapper = createWrapper({
            privileges: [],
            propsData: {
                entry: {
                    id: 'sw-product',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    icon: 'default-symbol-products',
                    parent: 'sw-catalogue',
                    position: 10,
                    moduleType: 'core',
                    level: 1,
                    children: [{
                        id: 'sw-product',
                        label: 'sw-product.general.mainMenuItemGeneral',
                        color: '#57D9A3',
                        path: 'sw.product.index',
                        icon: 'default-symbol-products',
                        parent: 'sw-catalogue',
                        position: 10,
                        level: 2,
                        moduleType: 'core',
                        privilege: 'product.viewer',
                        children: []
                    }, {
                        id: 'sw-review',
                        label: 'sw-review.general.mainMenuItemList',
                        color: '#57D9A3',
                        path: 'sw.review.index',
                        icon: 'default-symbol-products',
                        parent: 'sw-catalogue',
                        privilege: 'reviewer.viewer',
                        position: 20,
                        level: 2,
                        moduleType: 'core',
                        children: []
                    }]
                }
            }
        });

        expect(wrapper.html()).toBe('');
    });

    // eslint-disable-next-line max-len
    test('should show the menu entry when all children have privileges the user do not have but the main path is allowed', () => {
        const wrapper = createWrapper({
            privileges: [],
            propsData: {
                entry: {
                    id: 'sw-product',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    path: 'sw.cms.index',
                    icon: 'default-symbol-products',
                    parent: 'sw-catalogue',
                    position: 10,
                    moduleType: 'core',
                    level: 1,
                    children: [{
                        id: 'sw-product',
                        label: 'sw-product.general.mainMenuItemGeneral',
                        color: '#57D9A3',
                        path: 'sw.product.index',
                        icon: 'default-symbol-products',
                        parent: 'sw-catalogue',
                        position: 10,
                        level: 2,
                        moduleType: 'core',
                        privilege: 'product.viewer',
                        children: []
                    }, {
                        id: 'sw-review',
                        label: 'sw-review.general.mainMenuItemList',
                        color: '#57D9A3',
                        path: 'sw.review.index',
                        icon: 'default-symbol-products',
                        parent: 'sw-catalogue',
                        privilege: 'reviewer.viewer',
                        position: 20,
                        level: 2,
                        moduleType: 'core',
                        children: []
                    }]
                }
            }
        });

        const navigationLink = wrapper.find('.sw-admin-menu__navigation-link');
        expect(navigationLink.element.tagName).toBe('A');
        expect(navigationLink.props().to).toMatchObject({
            name: 'sw.cms.index'
        });
    });

    test('should hide settings menu if no item is visible', () => {
        Shopware.State.get('settingsItems').settingsGroups.shop = [
            { privilege: 'no-set', path: 'test' }
        ];

        const wrapper = createWrapper({
            privileges: [],
            propsData: {
                entry: {
                    id: 'sw-settings.index',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    path: 'sw.settings.index',
                    icon: 'default-symbol-products',
                    level: 1,
                    moduleType: 'core',
                    position: 10
                }
            }
        });

        expect(wrapper.html()).toBe('');
    });


    test('settings should be shown if all item is visible', () => {
        Shopware.State.get('settingsItems').settingsGroups.shop = [
            { privilege: 'priv-1' },
            { privilege: 'priv-2' }
        ];

        const wrapper = createWrapper({
            privileges: ['priv-1', 'priv2'],
            propsData: {
                entry: {
                    id: 'sw-settings.index',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    path: 'sw.settings.index',
                    icon: 'default-symbol-products',
                    position: 10,
                    level: 1,
                    moduleType: 'core',
                    children: []
                }
            }
        });

        expect(wrapper.html()).not.toBe('');
    });

    test('settings should be shown if one item is visible', () => {
        Shopware.State.get('settingsItems').settingsGroups.shop = [
            { privilege: 'priv-1' },
            { privilege: 'priv-2' }
        ];

        const wrapper = createWrapper({
            privileges: ['priv-1'],
            propsData: {
                entry: {
                    id: 'sw-settings.index',
                    label: 'sw-product.general.mainMenuItemGeneral',
                    color: '#57D9A3',
                    path: 'sw.settings.index',
                    icon: 'default-symbol-products',
                    position: 10,
                    level: 1,
                    moduleType: 'core',
                    children: []
                }
            }
        });

        expect(wrapper.html()).not.toBe('');
    });

    test('get the first plugin menu entry', () => {
        const wrapper = createWrapper({
            privileges: [],
            propsData: {
                entry: {
                    path: 'sw.foo.index',
                    label: 'sw-foo.general.mainMenuItemList',
                    id: 'sw-foo',
                    moduleType: 'plugin',
                    parent: 'sw-catalogue',
                    position: 1010,
                    children: [],
                    level: 2
                }
            }
        });

        expect(wrapper.vm.isFirstPluginInMenuEntries(wrapper.vm.entry, catalogues.children)).toBeTruthy();

        wrapper.setProps({
            entry: {
                path: 'sw.bar.index',
                label: 'sw-bar.general.mainMenuItemList',
                id: 'sw-bar',
                moduleType: 'plugin',
                parent: 'sw-catalogue',
                position: 1010,
                children: [],
                level: 2
            }
        });

        expect(wrapper.vm.isFirstPluginInMenuEntries(wrapper.vm.entry, catalogues.children)).toBeFalsy();
    });

    test('should match route', () => {
        const entries = catalogues.children;
        entries.unshift({
            id: 'sw-catalogue',
            moduleType: 'core',
            label: 'global.sw-admin-menu.navigation.mainMenuItemCatalogue',
            color: '#57D9A3',
            icon: 'default-symbol-products',
            position: 20,
            level: 1
        });

        Shopware.State.commit('adminMenu/setAdminModuleNavigation', entries);

        const wrapper = createWrapper({
            privileges: [],
            propsData: {
                entry: {
                    path: 'sw.foo.index',
                    label: 'sw-foo.general.mainMenuItemList',
                    id: 'sw-foo',
                    moduleType: 'plugin',
                    parent: 'sw-catalogue',
                    position: 1010,
                    children: [],
                    level: 2
                }
            }
        });

        expect(wrapper.vm.subIsActive('sw.foo.index'));
    });
});
