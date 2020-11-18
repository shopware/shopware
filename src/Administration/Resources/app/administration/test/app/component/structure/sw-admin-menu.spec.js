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
});
