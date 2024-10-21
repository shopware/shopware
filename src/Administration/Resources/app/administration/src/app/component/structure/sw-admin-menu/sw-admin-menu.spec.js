/**
 * @package admin
 */

import { mount, config } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';
import createMenuService from 'src/app/service/menu.service';
import catalogues from './_sw-admin-menu-item/catalogues';

/** fixtures */
import adminModules from '../../../service/_mocks/adminModules.json';
import testApps from '../../../service/_mocks/testApps.json';

const menuService = createMenuService(Shopware.Module);
Shopware.Service().register('menuService', () => menuService);

async function createWrapper(options = {}) {
    const router = createRouter({
        routes: [
            ...Shopware.Module.getModuleRoutes(),
            {
                path: '/sw/custom/entity/index',
                name: 'sw.custom.entity.index',
                type: 'core',
                components: { default: 'sw-index' },
                isChildren: false,
                routeKey: 'index',
            },
        ],
        route: {
            meta: {
                $module: {
                    name: '',
                },
            },
        },
        history: createWebHashHistory(),
    });

    router.resolve = jest.fn(() => {
        return {};
    });

    return mount(await wrapTestComponent('sw-admin-menu', { sync: true }), {
        global: {
            stubs: {
                'sw-icon': {
                    template: '<div class="sw-icon"></div>',
                },
                'sw-version': true,
                'sw-admin-menu-item': await wrapTestComponent('sw-admin-menu-item'),
                'sw-loader': true,
                'sw-avatar': true,
                'sw-shortcut-overview': true,
                'router-link': {
                    template: '<div class="router-link"><slot /></div>',
                },
            },
            provide: {
                menuService,
                loginService: {
                    notifyOnLoginListener: () => {},
                },
                userService: {
                    getUser: () => Promise.resolve({ data: { password: '' } }),
                },
                appModulesService: {
                    fetchAppModules: () => Promise.resolve([]),
                },
                acl: {
                    can: (privilege) => {
                        return privilege !== 'shouldReturnFalse';
                    },
                },
                customEntityDefinitionService: {
                    getMenuEntries: () => {
                        const entityName = 'customEntityName';
                        return [
                            {
                                id: `custom-entity/${entityName}`,
                                label: `${entityName}.moduleTitle`,
                                moduleType: 'plugin',
                                path: 'sw.custom.entity.index',
                                params: {
                                    entityName: entityName,
                                },
                                position: 100,
                                parent: 'sw.second.top.level',
                            },
                        ];
                    },
                },
            },
            mocks: {
                $route: { meta: { $module: { name: '' } } },
                $router: router,
            },
        },
        ...options,
    });
}

describe('src/app/component/structure/sw-admin-menu', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.State.get('session').currentLocale = 'en-GB';
        Shopware.Context.app.fallbackLocale = 'en-GB';

        if (Shopware.State.get('settingsItems')) {
            Shopware.State.unregisterModule('settingsItems');
        }

        Shopware.State.registerModule('settingsItems', {
            namespaced: true,
            state: {
                settingsGroups: {
                    shop: [],
                    system: [],
                },
            },
        });

        Shopware.Module.getModuleRegistry().clear();
        adminModules.forEach((adminModule) => {
            Shopware.Module.register(adminModule.name, adminModule);
        });
    });

    beforeEach(async () => {
        // This is here to fix v-bind false error for transition "persisted"
        config.global.stubs = {
            transition: false,
        };

        jest.spyOn(Shopware.Utils.debug, 'error').mockImplementation(() => true);

        Shopware.State.commit('setCurrentUser', null);
        Shopware.State.get('settingsItems').settingsGroups.shop = [];
        Shopware.State.get('settingsItems').settingsGroups.system = [];

        Shopware.State.commit('shopwareApps/setApps', []);

        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show the snippet for the admin title', async () => {
        Shopware.State.commit('setCurrentUser', {
            admin: true,
            title: 'Master of something',
            aclRoles: [],
        });

        await wrapper.vm.$nextTick();

        const userTitle = wrapper.find('.sw-admin-menu__user-type');

        expect(userTitle.text()).toBe('global.sw-admin-menu.administrator');
    });

    it('should show the user title for the non admin user', async () => {
        Shopware.State.commit('setCurrentUser', {
            admin: false,
            title: 'Master of something',
            aclRoles: [],
        });
        await wrapper.vm.$nextTick();

        const userTitle = wrapper.find('.sw-admin-menu__user-type');

        expect(userTitle.text()).toBe('Master of something');
    });

    it('should show no title when user has no title and no aclRoles defined', async () => {
        Shopware.State.commit('setCurrentUser', {
            admin: false,
            title: null,
            aclRoles: [],
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
                { name: 'Copyreader' },
            ],
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

        wrapper.vm.removeClassesFromElements(
            [
                element1,
                element2,
            ],
            ['foo'],
            [element2],
        );

        expect(element1.classList.contains('bar')).toBe(true);
        expect(element1.classList.contains('foo')).toBe(false);

        expect(element2.classList.contains('bar')).toBe(true);
        expect(element2.classList.contains('foo')).toBe(true);
    });

    it('should be able to check if a mouse position is in a polygon', async () => {
        const polygon = [
            [
                0,
                287,
            ],
            [
                0,
                335,
            ],
            [
                300,
                431,
            ],
            [
                300,
                287,
            ],
        ];

        const insideMousePosition = {
            x: 10,
            y: 300,
        };
        expect(wrapper.vm.isPositionInPolygon(insideMousePosition.x, insideMousePosition.y, polygon)).toBe(true);

        const outsideMousePosition = {
            x: 1,
            y: 1,
        };
        expect(wrapper.vm.isPositionInPolygon(outsideMousePosition.x, outsideMousePosition.y, polygon)).toBe(false);
    });

    it('should get polygon from menu item', async () => {
        const element = document.createElement('div');
        const entry = {
            children: [
                {
                    name: 'foo',
                },
            ],
        };

        expect(wrapper.vm.getPolygonFromMenuItem(element, entry)).toStrictEqual([
            [
                0,
                0,
            ],
            [
                0,
                0,
            ],
            [
                0,
                0,
            ],
            [
                0,
                0,
            ],
        ]);
    });

    it('should render correct admin menu entries', async () => {
        const topLevelEntries = wrapper.findAllComponents('.navigation-list-item__level-1');

        // expect two top level entries visible because sw-my-apps and second-module have no children nor a path
        expect(topLevelEntries).toHaveLength(2);

        const topLevelEntry = topLevelEntries.at(0);
        expect(topLevelEntry.text()).toContain('second top level entry');

        const childMenuEntries = topLevelEntry.findAll('.navigation-list-item__level-2');

        expect(childMenuEntries).toHaveLength(4);

        const expectedTexts = [
            'first child of second top level entry',
            'second child of second top level entry',
            'last child of second top level entry',
            'customEntityName.moduleTitle',
        ];

        childMenuEntries.forEach((childMenuEntry, index) => {
            expect(childMenuEntry.text()).toContain(expectedTexts[index]);
        });
    });

    it('should render third level menu correctly', async () => {
        const thirdLevelEntries = wrapper.findAll('.navigation-list-item__level-3');

        expect(thirdLevelEntries).toHaveLength(1);
        expect(thirdLevelEntries.at(0).text()).toContain('first child of third top level entry');
    });

    it('should not render 4.level or higher menu item and throw error', async () => {
        const fourthLevelEntries = wrapper.findAll('.navigation-list-item__level-4');
        const fifthLevelEntries = wrapper.findAll('.navigation-list-item__level-5');

        // Levels dont get rendered
        expect(fourthLevelEntries).toHaveLength(0);
        expect(fifthLevelEntries).toHaveLength(0);

        // Console error gets thrown for both levels
        expect(Shopware.Utils.debug.error.mock.calls[0][0]).toBeInstanceOf(Error);
        expect(Shopware.Utils.debug.error.mock.calls[0][0].toString()).toBe(
            'Error: The navigation entry "sw.fourth.level.first" is nested on level 4 or higher.The admin menu only supports up to three levels of nesting.',
        );

        expect(Shopware.Utils.debug.error.mock.calls[1][0]).toBeInstanceOf(Error);
        expect(Shopware.Utils.debug.error.mock.calls[1][0].toString()).toBe(
            'Error: The navigation entry "sw.fifth.level.first" is nested on level 4 or higher.The admin menu only supports up to three levels of nesting.',
        );
    });

    it('should check privileges for main menu entry children', async () => {
        const topLevelEntries = wrapper.findAll('.navigation-list-item__level-1');

        expect(topLevelEntries).toHaveLength(2);

        const topLevelEntry = topLevelEntries.at(1);
        expect(topLevelEntry.text()).toContain('children menu entry');

        const childMenuEntries = topLevelEntry.findAll('.navigation-list-item__level-2');

        // Only one children should be shown, the other has acl privileges
        expect(childMenuEntries).toHaveLength(1);
        expect(childMenuEntries.at(0).text()).toContain('Entry without privilege');
    });

    describe('app menu entries', () => {
        it('renders apps under there parent navigation entry', async () => {
            Shopware.State.commit('shopwareApps/setApps', testApps);
            await flushPromises();

            const topLevelEntries = wrapper.findAll('.navigation-list-item__level-1');
            const childMenuEntries = topLevelEntries.at(1).findAll('.navigation-list-item__level-2');

            const expectedTexts = [
                'Module without position',
                'first child of second top level entry',
                'second child of second top level entry',
                'last child of second top level entry',
                'customEntityName.moduleTitle',
            ];

            childMenuEntries.forEach((childMenuEntry, index) => {
                expect(childMenuEntry.text()).toContain(expectedTexts[index]);
            });
        });

        it('renders app structure elements and their children', async () => {
            Shopware.State.commit('shopwareApps/setApps', testApps);
            await flushPromises();

            const topLevelEntries = wrapper.findAll('.navigation-list-item__level-1');
            const structureElement = topLevelEntries.at(0).get('.navigation-list-item__level-2');

            expect(structureElement.text()).toContain('Structure module');

            const appMenuEntry = structureElement.get('.navigation-list-item__level-3');

            expect(appMenuEntry.text()).toContain('Default module');
        });
    });

    it('get the first plugin menu entry', () => {
        let entry = {
            path: 'sw.foo.index',
            label: 'sw-foo.general.mainMenuItemList',
            id: 'sw-foo',
            moduleType: 'plugin',
            parent: 'sw-catalogue',
            position: 1010,
            children: [],
            level: 2,
        };

        expect(wrapper.vm.isFirstPluginInMenuEntries(entry, catalogues.children)).toBe(true);

        entry = {
            path: 'sw.bar.index',
            label: 'sw-bar.general.mainMenuItemList',
            id: 'sw-bar',
            moduleType: 'plugin',
            parent: 'sw-catalogue',
            position: 1010,
            children: [],
            level: 2,
        };

        expect(wrapper.vm.isFirstPluginInMenuEntries(entry, catalogues.children)).toBe(false);
    });

    it('positioning of flyout should respect top app border', async () => {
        const app = document.createElement('div');
        app.id = 'app';
        document.body.appendChild(app);
        const component = document.createElement('div');
        component.id = 'component';
        app.appendChild(component);

        wrapper = await createWrapper({
            attachTo: '#component',
        });
        await flushPromises();

        const target = wrapper.find('.navigation-list-item__has-children');

        target.element.getBoundingClientRect = jest.fn(() => ({ top: 100 }));
        app.getBoundingClientRect = jest.fn(() => ({ top: 20 }));

        await target.trigger('mouseenter');
        await flushPromises();

        expect(wrapper.vm.flyoutStyle.top).toBe('80px');
    });

    it('should not show icons in flyout menu items', async () => {
        const app = document.createElement('div');
        app.id = 'app';
        document.body.appendChild(app);
        const component = document.createElement('div');
        component.id = 'component';
        app.appendChild(component);

        wrapper = await createWrapper({
            attachTo: '#component',
        });
        await flushPromises();

        const target = wrapper.find('.navigation-list-item__has-children');

        target.element.getBoundingClientRect = jest.fn(() => ({ top: 100 }));
        app.getBoundingClientRect = jest.fn(() => ({ top: 20 }));

        await target.trigger('mouseenter');
        await flushPromises();

        const flyoutItem = wrapper.findComponent(
            '.sw-admin-menu_flyout-holder .navigation-list-item__sw-second-level-first',
        );
        expect(flyoutItem.findAll('.sw-icon')).toHaveLength(0);
    });
});
