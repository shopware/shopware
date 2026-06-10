/* eslint-disable sw-test-rules/test-file-max-lines-warning */

/**
 * @sw-package framework
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
                'sw-version': true,
                'sw-admin-menu-item': await wrapTestComponent('sw-admin-menu-item'),
                'sw-loader': true,
                'sw-avatar': true,
                'sw-shortcut-overview': true,
                'router-link': {
                    template: '<div class="router-link"><slot /></div>',
                },
                'mt-link': true,
                'mt-icon': true,
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
        Shopware.Store.get('session').currentLocale = 'en-GB';
        Shopware.Context.app.fallbackLocale = 'en-GB';

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

        Shopware.Store.get('session').setCurrentUser(null);
        Shopware.Store.get('settingsItems').settingsGroups.shop = [];
        Shopware.Store.get('settingsItems').settingsGroups.system = [];

        Shopware.Store.get('shopwareApps').apps = [];
        Shopware.Store.get('adminMenu').clearExpandedMenuEntries();

        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should show the snippet for the admin title', async () => {
        Shopware.Store.get('session').setCurrentUser({
            admin: true,
            title: 'Master of something',
            aclRoles: [],
        });

        await wrapper.vm.$nextTick();

        const userTitle = wrapper.find('.sw-admin-menu__user-type');

        expect(userTitle.text()).toBe('global.sw-admin-menu.administrator');
    });

    it('should show the user title for the non admin user', async () => {
        Shopware.Store.get('session').setCurrentUser({
            admin: false,
            title: 'Master of something',
            aclRoles: [],
        });
        await wrapper.vm.$nextTick();

        const userTitle = wrapper.find('.sw-admin-menu__user-type');

        expect(userTitle.text()).toBe('Master of something');
    });

    it('should show no title when user has no title and no aclRoles defined', async () => {
        Shopware.Store.get('session').setCurrentUser({
            admin: false,
            title: null,
            aclRoles: [],
        });
        await wrapper.vm.$nextTick();

        const userTitle = wrapper.find('.sw-admin-menu__user-type');

        expect(userTitle.text()).toBe('');
    });

    it('should use the name of the first acl role as a title when user has no title defined', async () => {
        Shopware.Store.get('session').setCurrentUser({
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

    it('should close off-canvas menu on route changes on mobile', async () => {
        const emitSpy = jest.spyOn(Shopware.Utils.EventBus, 'emit');

        wrapper.vm.$device.getViewportWidth.mockReturnValue(375);
        await wrapper.setData({ isOffCanvasShown: true });

        wrapper.vm.$options.watch.$route.call(wrapper.vm);

        expect(wrapper.vm.isOffCanvasShown).toBe(false);
        expect(emitSpy).toHaveBeenCalledWith('sw-admin-menu/toggle-offcanvas', false);

        wrapper.vm.$device.getViewportWidth.mockReturnValue(1920);
        emitSpy.mockRestore();
    });

    it('should not close off-canvas menu on route changes on desktop', async () => {
        const emitSpy = jest.spyOn(Shopware.Utils.EventBus, 'emit');

        wrapper.vm.$device.getViewportWidth.mockReturnValue(1920);
        await wrapper.setData({ isOffCanvasShown: true });

        wrapper.vm.$options.watch.$route.call(wrapper.vm);

        expect(wrapper.vm.isOffCanvasShown).toBe(true);
        expect(emitSpy).not.toHaveBeenCalledWith('sw-admin-menu/toggle-offcanvas', false);

        emitSpy.mockRestore();
    });

    it('should close off-canvas menu when clicking a navigation item on mobile', async () => {
        const emitSpy = jest.spyOn(Shopware.Utils.EventBus, 'emit');
        const target = document.createElement('li');
        target.classList.add('sw-admin-menu__navigation-list-item');

        wrapper.vm.$device.getViewportWidth.mockReturnValue(375);
        await wrapper.setData({ isOffCanvasShown: true });

        wrapper.vm.onMenuItemClick({ level: 1 }, target);

        expect(wrapper.vm.isOffCanvasShown).toBe(false);
        expect(emitSpy).toHaveBeenCalledWith('sw-admin-menu/toggle-offcanvas', false);

        wrapper.vm.$device.getViewportWidth.mockReturnValue(1920);
        emitSpy.mockRestore();
    });

    it('should not close off-canvas menu when clicking a navigation item on desktop', async () => {
        const emitSpy = jest.spyOn(Shopware.Utils.EventBus, 'emit');
        const target = document.createElement('li');
        target.classList.add('sw-admin-menu__navigation-list-item');

        wrapper.vm.$device.getViewportWidth.mockReturnValue(1920);
        await wrapper.setData({ isOffCanvasShown: true });

        wrapper.vm.onMenuItemClick({ level: 1 }, target);

        expect(wrapper.vm.isOffCanvasShown).toBe(true);
        expect(emitSpy).not.toHaveBeenCalledWith('sw-admin-menu/toggle-offcanvas', false);

        emitSpy.mockRestore();
    });

    it('should close off-canvas menu when clicking outside on mobile', async () => {
        const emitSpy = jest.spyOn(Shopware.Utils.EventBus, 'emit');
        const outsideElement = document.createElement('button');
        document.body.appendChild(outsideElement);

        wrapper.vm.$device.getViewportWidth.mockReturnValue(375);
        await wrapper.setData({ isOffCanvasShown: true });

        outsideElement.dispatchEvent(new MouseEvent('click', { bubbles: true }));

        expect(wrapper.vm.isOffCanvasShown).toBe(false);
        expect(emitSpy).toHaveBeenCalledWith('sw-admin-menu/toggle-offcanvas', false);

        outsideElement.remove();
        wrapper.vm.$device.getViewportWidth.mockReturnValue(1920);
        emitSpy.mockRestore();
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
            Shopware.Store.get('shopwareApps').apps = testApps;
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
            Shopware.Store.get('shopwareApps').apps = testApps;
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
        expect(wrapper.vm.flyoutStyle['max-height']).toBe(`${window.innerHeight - 100}px`);
    });

    it('should constrain flyout max-height when hovering a menu item near the viewport bottom', async () => {
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

        // Target sits 40px above the bottom of the viewport
        const targetTop = window.innerHeight - 40;
        target.element.getBoundingClientRect = jest.fn(() => ({ top: targetTop }));
        app.getBoundingClientRect = jest.fn(() => ({ top: 0 }));

        await target.trigger('mouseenter');
        await flushPromises();

        expect(wrapper.vm.flyoutStyle['max-height']).toBe('40px');
    });

    it('should set flyout max-height to the full viewport height when target is at the top', async () => {
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

        target.element.getBoundingClientRect = jest.fn(() => ({ top: 0 }));
        app.getBoundingClientRect = jest.fn(() => ({ top: 0 }));

        await target.trigger('mouseenter');
        await flushPromises();

        expect(wrapper.vm.flyoutStyle['max-height']).toBe(`${window.innerHeight}px`);
    });

    it('should position the existing third level submenu in an expanded menu item', async () => {
        const topLevelEntry = wrapper.get('.navigation-list-item__sw-second-top-level');

        await topLevelEntry.trigger('click');
        await flushPromises();

        const target = wrapper.get('.navigation-list-item__sw-second-level-last');
        target.element.getBoundingClientRect = jest.fn(() => ({
            top: 160,
            right: 560,
        }));

        await target.trigger('mouseenter');

        const subNavigationList = target.get('.sw-admin-menu__sub-navigation-list');

        expect(wrapper.find('.sw-admin-menu_flyout-holder').exists()).toBe(false);
        expect(target.classes()).toContain('is--flyout-enabled');
        expect(subNavigationList.element.style.position).toBe('fixed');
        expect(subNavigationList.element.style.top).toBe('160px');
        expect(subNavigationList.element.style.left).toBe('560px');
        expect(subNavigationList.element.style.maxHeight).toBe(`${window.innerHeight - 160}px`);
        expect(subNavigationList.element.style.overflowY).toBe('auto');
        expect(subNavigationList.element.style.transition).toBe('none');
    });

    it('should position and clean up the third level submenu in the primary flyout', async () => {
        document.body.innerHTML = '<div id="app"></div>';
        const app = document.getElementById('app');

        wrapper = await createWrapper({
            attachTo: app,
        });
        await flushPromises();

        const topLevelEntry = wrapper.get('.navigation-list-item__sw-second-top-level');
        topLevelEntry.element.getBoundingClientRect = jest.fn(() => ({ top: 100 }));
        app.getBoundingClientRect = jest.fn(() => ({ top: 0 }));

        await topLevelEntry.trigger('mouseenter');
        await flushPromises();

        const flyout = wrapper.get('.sw-admin-menu_flyout-holder');
        const target = flyout.get('.navigation-list-item__sw-second-level-last');
        target.element.getBoundingClientRect = jest.fn(() => ({
            top: 180,
            right: 540,
        }));

        await target.trigger('mouseenter');

        const subNavigationList = target.get('.sw-admin-menu__sub-navigation-list');

        expect(target.classes()).toContain('is--flyout-enabled');
        expect(subNavigationList.element.style.position).toBe('fixed');
        expect(subNavigationList.element.style.top).toBe('180px');
        expect(subNavigationList.element.style.left).toBe('540px');
        expect(subNavigationList.element.style.maxHeight).toBe(`${window.innerHeight - 180}px`);
        expect(subNavigationList.element.style.overflowY).toBe('auto');
        expect(subNavigationList.element.style.transition).toBe('none');

        const leafTarget = flyout.get('.navigation-list-item__sw-second-level-first');

        await leafTarget.trigger('mouseenter');

        expect(target.classes()).not.toContain('is--flyout-enabled');
        expect(wrapper.find('.sw-admin-menu_flyout-holder').exists()).toBe(true);
    });

    it('should call logoutSso and clear stores on logout', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.loginService.logoutSso = jest.fn().mockResolvedValue(undefined);

        await wrapper.vm.onLogoutUser();

        expect(wrapper.vm.loginService.logoutSso).toHaveBeenCalledTimes(1);
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
        expect(flyoutItem.findAll('.mt-icon')).toHaveLength(0);
    });
});
