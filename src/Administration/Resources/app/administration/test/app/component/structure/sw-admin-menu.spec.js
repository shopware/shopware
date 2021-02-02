import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/app/component/structure/sw-admin-menu';
import mainMenu from './_mocks/mainMenu.json';

function createWrapper() {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-admin-menu'), {
        localVue,
        sync: false,
        stubs: {
            'sw-icon': true,
            'sw-version': true,
            'sw-admin-menu-item': true,
            'sw-loader': true,
            'sw-avatar': true,
            'sw-shortcut-overview': true
        },
        mocks: {
            $tc: key => key,
            $device: {
                onResize: () => {},
                getViewportWidth: () => {}
            }
        },
        provide: {
            feature: {
                isActive: () => true
            },
            menuService: {
                getMainMenu: () => {
                    return mainMenu;
                }
            },
            loginService: {
                notifyOnLoginListener: () => {}
            },
            userService: {
                getUser: () => Promise.resolve({})
            },
            appModulesService: {
                fetchAppModules: () => Promise.resolve([])
            }
        }
    });
}


describe('src/app/component/structure/sw-admin-menu', () => {
    let wrapper = createWrapper();

    beforeAll(() => {
        Shopware.Feature.isActive = () => true;

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
        Shopware.State.commit('setCurrentUser', null);
        Shopware.State.get('settingsItems').settingsGroups.shop = [];
        Shopware.State.get('settingsItems').settingsGroups.system = [];

        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show the snippet for the admin title', async () => {
        Shopware.State.commit('setCurrentUser', {
            admin: true,
            title: 'Master of something',
            aclRoles: []
        });
        wrapper = await createWrapper();

        const userTitle = wrapper.find('.sw-admin-menu__user-type');

        expect(userTitle.text()).toBe('global.sw-admin-menu.administrator');
    });

    it('should show the user title for the non admin user', async () => {
        Shopware.State.commit('setCurrentUser', {
            admin: false,
            title: 'Master of something',
            aclRoles: []
        });
        wrapper = await createWrapper();

        const userTitle = wrapper.find('.sw-admin-menu__user-type');

        expect(userTitle.text()).toBe('Master of something');
    });

    it('should show no title when user has no title and no aclRoles defined', async () => {
        Shopware.State.commit('setCurrentUser', {
            admin: false,
            title: null,
            aclRoles: []
        });
        wrapper = await createWrapper();

        const userTitle = wrapper.find('.sw-admin-menu__user-type');

        expect(userTitle.text()).toBe('');
    });

    it('should use the name of the first acl role as a title when user has no title defined', async () => {
        Shopware.State.commit('setCurrentUser', {
            admin: false,
            title: null,
            aclRoles: [
                { name: 'Copyreader' }
            ]
        });

        wrapper = await createWrapper();

        const userTitle = wrapper.find('.sw-admin-menu__user-type');

        expect(userTitle.text()).toBe('Copyreader');
    });

    it('should remove classes from an element', async () => {
        wrapper = await createWrapper();

        const element1 = document.createElement('div');
        const element2 = document.createElement('div');

        element1.classList.add('foo', 'bar');
        element2.classList.add('foo', 'bar');

        wrapper.vm.removeClassesFromElements([
            element1,
            element2
        ], ['foo'], [element2]);

        expect(element1.classList.contains('bar')).toBeTruthy();
        expect(element1.classList.contains('foo')).toBeFalsy();

        expect(element2.classList.contains('bar')).toBeTruthy();
        expect(element2.classList.contains('foo')).toBeTruthy();
    });

    it('should be able to check if a mouse position is in a polygon', async () => {
        wrapper = await createWrapper();

        const polygon = [
            [0, 287],
            [0, 335],
            [300, 431],
            [300, 287]
        ];

        const insideMousePosition = {
            x: 10,
            y: 300
        };
        expect(wrapper.vm.isPositionInPolygon(insideMousePosition.x, insideMousePosition.y, polygon)).toBeTruthy();

        const outsideMousePosition = {
            x: 1,
            y: 1
        };
        expect(wrapper.vm.isPositionInPolygon(outsideMousePosition.x, outsideMousePosition.y, polygon)).toBeFalsy();
    });

    it('should get polygon from menu item', async () => {
        wrapper = await createWrapper();

        const element = document.createElement('div');
        const entry = {
            children: [{
                name: 'foo'
            }]
        };

        expect(wrapper.vm.getPolygonFromMenuItem(element, entry)).toStrictEqual([[0, 0], [0, 0], [0, 0], [0, 0]]);
    });
});
