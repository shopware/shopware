import { shallowMount } from '@vue/test-utils';
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
                }
            }
        }
    });
}

describe('src/app/component/structure/sw-admin-menu-item', () => {
    beforeAll(() => {
        Shopware.FeatureConfig.isActive = () => true;
    });

    it('should be a Vue.js component', () => {
        const wrapper = createWrapper({
            propsData: {
                entry: catalogues
            }
        });

        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should contain all menu entries', () => {
        const wrapper = createWrapper({
            propsData: {
                entry: catalogues
            }
        });

        const children = wrapper.findAll('.sw-admin-menu__sub-navigation-list .sw-admin-menu__navigation-list-item');
        expect(children.length).toBe(6);

        expect(wrapper.classes()).toContain('sw-catalogue');
        expect(children.at(0).classes()).toContain('sw-product');
        expect(children.at(1).classes()).toContain('sw-review');
        expect(children.at(2).classes()).toContain('sw-category');
        expect(children.at(3).classes()).toContain('sw-product-stream');
        expect(children.at(4).classes()).toContain('sw-property');
        expect(children.at(5).classes()).toContain('sw-manufacturer');

        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should show only one entry without children', () => {
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

        expect(wrapper.classes()).toContain('sw-product');
    });

    it('should show a link when a path is provided', () => {
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

        const routerLink = wrapper.find('.sw-admin-menu__navigation-list-item.sw-product .router-link');

        expect(routerLink.props().to).toMatchObject({
            name: 'sw.product.index'
        });
    });

    it('should not show a link when no path is provided', () => {
        const wrapper = createWrapper({
            propsData: {
                entry: catalogues
            }
        });

        const navigationLink = wrapper.find('.sw-admin-menu__navigation-link');
        expect(navigationLink.is('span')).toBeTruthy();
    });

    it('should not show the menu entry when user has no privilege', () => {
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
                    children: []
                }
            }
        });

        expect(wrapper.html()).toBeUndefined();
    });

    it('should show the menu entry when user has the privilege', () => {
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
                    children: []
                }
            }
        });

        expect(wrapper.html()).toBeDefined();
    });

    it('should not show a link when the path goes to a route which needs a privilege which is not set', () => {
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
                    children: [{
                        id: 'sw-product',
                        label: 'sw-product.general.mainMenuItemGeneral',
                        color: '#57D9A3',
                        path: 'sw.product.index',
                        icon: 'default-symbol-products',
                        parent: 'sw-catalogue',
                        position: 10,
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
                        children: []
                    }]
                }
            }
        });

        const navigationLink = wrapper.find('.sw-admin-menu__navigation-link');
        expect(navigationLink.is('span')).toBeTruthy();
    });

    it('should show a link when the path goes to a route which needs a privilege which is set', () => {
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
                    children: [{
                        id: 'sw-product',
                        label: 'sw-product.general.mainMenuItemGeneral',
                        color: '#57D9A3',
                        path: 'sw.product.index',
                        icon: 'default-symbol-products',
                        parent: 'sw-catalogue',
                        position: 10,
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
                        children: []
                    }]
                }
            }
        });

        const navigationLink = wrapper.find('.sw-admin-menu__navigation-link');
        expect(navigationLink.is('span')).toBeFalsy();
        expect(navigationLink.is('a')).toBeTruthy();

        expect(navigationLink.props().to).toMatchObject({
            name: 'sw.product.index'
        });
    });

    // eslint-disable-next-line max-len
    it('should not show the menu entry when all children have privileges the user do not have and the main path is also restricted', () => {
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
                    children: [{
                        id: 'sw-product',
                        label: 'sw-product.general.mainMenuItemGeneral',
                        color: '#57D9A3',
                        path: 'sw.product.index',
                        icon: 'default-symbol-products',
                        parent: 'sw-catalogue',
                        position: 10,
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
                        children: []
                    }]
                }
            }
        });

        expect(wrapper.html()).toBeUndefined();
    });

    it('should not show the menu entry when all children have privileges the user do not have', () => {
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
                    children: [{
                        id: 'sw-product',
                        label: 'sw-product.general.mainMenuItemGeneral',
                        color: '#57D9A3',
                        path: 'sw.product.index',
                        icon: 'default-symbol-products',
                        parent: 'sw-catalogue',
                        position: 10,
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
                        children: []
                    }]
                }
            }
        });

        expect(wrapper.html()).toBeUndefined();
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
                    children: [{
                        id: 'sw-product',
                        label: 'sw-product.general.mainMenuItemGeneral',
                        color: '#57D9A3',
                        path: 'sw.product.index',
                        icon: 'default-symbol-products',
                        parent: 'sw-catalogue',
                        position: 10,
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
                        children: []
                    }]
                }
            }
        });

        const navigationLink = wrapper.find('.sw-admin-menu__navigation-link');
        expect(navigationLink.is('a')).toBeTruthy();
        expect(navigationLink.props().to).toMatchObject({
            name: 'sw.cms.index'
        });
    });
});
