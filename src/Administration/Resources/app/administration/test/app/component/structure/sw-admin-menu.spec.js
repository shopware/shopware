import { config, shallowMount, createLocalVue } from '@vue/test-utils';
import VueRouter from 'vue-router';
import 'src/app/component/structure/sw-admin-menu';
import 'src/app/component/structure/sw-admin-menu-item';
import createMenuService from 'src/app/service/menu.service';
import catalogues from './_sw-admin-menu-item/catalogues';

/** fixtures */
import adminModules from '../../service/_mocks/adminModules.json';
import testApps from '../../service/_mocks/testApps.json';

const menuService = createMenuService(Shopware.Module);
Shopware.Service().register('menuService', () => menuService);

function createWrapper() {
    // delete global $router and $routes mocks
    delete config.mocks.$router;
    delete config.mocks.$route;

    const localVue = createLocalVue();
    localVue.directive('tooltip', {});
    localVue.use(VueRouter);

    const adminMenuComponent = Shopware.Component.build('sw-admin-menu');

    return shallowMount(adminMenuComponent, {
        localVue,
        router: new VueRouter({ routes: Shopware.Module.getModuleRoutes() }),
        stubs: {
            'sw-icon': true,
            'sw-version': true,
            'sw-admin-menu-item': Shopware.Component.build('sw-admin-menu-item'),
            'sw-loader': true,
            'sw-avatar': true,
            'sw-shortcut-overview': true
        },
        provide: {
            menuService,
            loginService: {
                notifyOnLoginListener: () => {}
            },
            userService: {
                getUser: () => Promise.resolve({})
            },
            appModulesService: {
                fetchAppModules: () => Promise.resolve([])
            },
            acl: { can: () => true }
        }
    });
}

describe('src/app/component/structure/sw-admin-menu', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.State.get('session').currentLocale = 'en-GB';
        Shopware.Context.app.fallbackLocale = 'en-GB';

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

        Shopware.Module.getModuleRegistry().clear();
        adminModules.forEach((adminModule) => {
            Shopware.Module.register(adminModule.name, adminModule);
        });

        Shopware.State.commit('shopwareApps/setApps', []);

        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    //

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show the snippet for the admin title', async () => {
        Shopware.State.commit('setCurrentUser', {
            admin: true,
            title: 'Master of something',
            aclRoles: []
        });

        await wrapper.vm.$nextTick();

        const userTitle = wrapper.find('.sw-admin-menu__user-type');

        expect(userTitle.text()).toBe('global.sw-admin-menu.administrator');
    });

    it('should show the user title for the non admin user', async () => {
        Shopware.State.commit('setCurrentUser', {
            admin: false,
            title: 'Master of something',
            aclRoles: []
        });
        await wrapper.vm.$nextTick();

        const userTitle = wrapper.find('.sw-admin-menu__user-type');

        expect(userTitle.text()).toBe('Master of something');
    });

    it('should show no title when user has no title and no aclRoles defined', async () => {
        Shopware.State.commit('setCurrentUser', {
            admin: false,
            title: null,
            aclRoles: []
        });
        await wrapper.vm.$nextTick();

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

        await wrapper.vm.$nextTick();


        const userTitle = wrapper.find('.sw-admin-menu__user-type');

        expect(userTitle.text()).toBe('Copyreader');
    });

    it('should remove classes from an element', async () => {
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

    it('should be able to check if a mouse position is in a polygon', () => {
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

    it('should get polygon from menu item', () => {
        const element = document.createElement('div');
        const entry = {
            children: [{
                name: 'foo'
            }]
        };

        expect(wrapper.vm.getPolygonFromMenuItem(element, entry))
            .toStrictEqual([[0, 0], [0, 0], [0, 0], [0, 0]]);
    });

    it('should render correct admin menu entries', () => {
        const topLevelEntries = wrapper.findAll('.navigation-list-item__level-1');

        // expect only one top level entry visible because sw-my-apps and second-module have no children nor a path
        expect(topLevelEntries).toHaveLength(1);

        const topLevelEntry = topLevelEntries.at(0);
        expect(topLevelEntry.props('entry')).toEqual(expect.objectContaining({
            id: 'sw.second.top.level'
        }));

        const childMenuEntries = topLevelEntry.findAll('.navigation-list-item__level-2');

        expect(childMenuEntries).toHaveLength(3);
        expect(childMenuEntries.wrappers.map((childMenuEntry) => {
            return childMenuEntry.props('entry');
        })).toEqual([
            expect.objectContaining({
                id: 'sw.second.level.first'
            }), expect.objectContaining({
                id: 'sw.second.level.second'
            }), expect.objectContaining({
                id: 'sw.second.level.last'
            })
        ]);
    });

    describe('app menu entries', () => {
        it('renders apps under there parent navigation entry', async () => {
            Shopware.State.commit('shopwareApps/setApps', testApps);
            await wrapper.vm.$nextTick();

            const topLevelEntries = wrapper.findAll('.navigation-list-item__level-1');
            const childMenuEntries = topLevelEntries.at(1).findAll('.navigation-list-item__level-2');

            expect(childMenuEntries.wrappers.map((menuEntry) => {
                return menuEntry.props('entry');
            })).toEqual(expect.arrayContaining([
                expect.objectContaining({
                    id: 'app-testAppA-noPosition'
                })
            ]));
        });

        it('renders app structure elements and their children', async () => {
            Shopware.State.commit('shopwareApps/setApps', testApps);
            await wrapper.vm.$nextTick();

            const topLevelEntries = wrapper.findAll('.navigation-list-item__level-1');
            const structureElement = topLevelEntries.at(0).get('.navigation-list-item__level-2');

            expect(structureElement.props('entry')).toEqual(
                expect.objectContaining({
                    id: 'app-testAppB-structure'
                })
            );

            const appMenuEntry = structureElement.get('.navigation-list-item__level-3');

            expect(appMenuEntry.props('entry')).toEqual(
                expect.objectContaining({
                    id: 'app-testAppB-default'
                })
            );
        });
    });

    describe('deprecated functionality', () => {
        it('renders app menu items without parent underneath my apps', async () => {
            Shopware.State.commit('shopwareApps/setApps', testApps);
            await wrapper.vm.$nextTick();

            const topLevelEntries = wrapper.findAll('.navigation-list-item__level-1');
            const appMenuEntry = topLevelEntries.at(2).get('.navigation-list-item__level-2');

            expect(appMenuEntry.props('entry')).toEqual(
                expect.objectContaining({
                    id: 'app-testAppA-noParent'
                })
            );
        });
    });

    test('get the first plugin menu entry', () => {
        let entry = {
            path: 'sw.foo.index',
            label: 'sw-foo.general.mainMenuItemList',
            id: 'sw-foo',
            moduleType: 'plugin',
            parent: 'sw-catalogue',
            position: 1010,
            children: [],
            level: 2
        };

        expect(wrapper.vm.isFirstPluginInMenuEntries(entry, catalogues.children)).toBeTruthy();

        entry = {
            path: 'sw.bar.index',
            label: 'sw-bar.general.mainMenuItemList',
            id: 'sw-bar',
            moduleType: 'plugin',
            parent: 'sw-catalogue',
            position: 1010,
            children: [],
            level: 2
        };

        expect(wrapper.vm.isFirstPluginInMenuEntries(entry, catalogues.children)).toBeFalsy();
    });
});
