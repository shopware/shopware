/**
 * @sw-package framework
 */

import { config, DOMWrapper } from '@vue/test-utils';
import createWrapper, { registerAdminModules } from './sw-admin-menu.spec/create-wrapper';

/** fixtures */
import testApps from '../../../service/_mocks/testApps.json';

describe('src/app/component/structure/sw-admin-menu', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.Store.get('session').currentLocale = 'en-GB';
        Shopware.Context.app.fallbackLocale = 'en-GB';

        registerAdminModules();
    });

    beforeEach(async () => {
        // Fixes the v-bind false error for transition "persisted"; merged so the
        // globally registered meteor components stay resolvable
        config.global.stubs = {
            ...config.global.stubs,
            transition: false,
        };

        jest.spyOn(Shopware.Utils.debug, 'error').mockImplementation(() => true);

        Shopware.Store.get('session').setCurrentUser(null);
        Shopware.Store.get('settingsItems').settingsGroups.shop = [];
        Shopware.Store.get('settingsItems').settingsGroups.system = [];

        Shopware.Store.get('shopwareApps').apps = [];

        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should show the configured shop name', async () => {
        wrapper.vm.systemConfigApiService.getValues = () => Promise.resolve({ 'core.basicInformation.shopName': 'My Shop' });

        wrapper.vm.loadShopName();
        await flushPromises();

        expect(wrapper.find('.sw-admin-menu__shop-name').text()).toBe('My Shop');
    });

    it('should fall back to "Shopware" when no shop name is configured', async () => {
        await flushPromises();

        expect(wrapper.find('.sw-admin-menu__shop-name').text()).toBe('Shopware');
    });

    it('should fall back to "Shopware" when the shop name cannot be loaded', async () => {
        wrapper.vm.shopName = '';
        wrapper.vm.systemConfigApiService.getValues = () => Promise.reject(new Error('forbidden'));

        wrapper.vm.loadShopName();
        await flushPromises();

        expect(wrapper.vm.shopName).toBe('Shopware');
    });

    it('should keep the branch with the active child open when another branch is opened', async () => {
        const branches = wrapper.vm.mainMenuEntries.filter((entry) => (entry.children?.length ?? 0) > 0);
        expect(branches.length).toBeGreaterThanOrEqual(2);

        const [
            activeBranch,
            otherBranch,
        ] = branches;

        wrapper.vm.$route.name = activeBranch.children[0].path;
        Shopware.Store.get('adminMenu').clearExpandedMenuEntries();

        wrapper.vm.onMenuBranchToggle({ entry: activeBranch, open: true });
        wrapper.vm.onMenuBranchToggle({ entry: otherBranch, open: true });

        expect(wrapper.vm.isNavigationEntryExpanded(activeBranch)).toBe(true);
        expect(wrapper.vm.isNavigationEntryExpanded(otherBranch)).toBe(true);
    });

    it('should close a branch without an active child when another branch is opened', async () => {
        const branches = wrapper.vm.mainMenuEntries.filter((entry) => (entry.children?.length ?? 0) > 0);

        const [
            branchA,
            branchB,
        ] = branches;

        wrapper.vm.$route.name = undefined;
        Shopware.Store.get('adminMenu').clearExpandedMenuEntries();

        wrapper.vm.onMenuBranchToggle({ entry: branchA, open: true });
        wrapper.vm.onMenuBranchToggle({ entry: branchB, open: true });

        expect(wrapper.vm.isNavigationEntryExpanded(branchA)).toBe(false);
        expect(wrapper.vm.isNavigationEntryExpanded(branchB)).toBe(true);
    });

    it('should close the previous branch when the active item moves to another branch', async () => {
        const branches = wrapper.vm.mainMenuEntries.filter((entry) => (entry.children?.length ?? 0) > 0);

        const [
            branchA,
            branchB,
        ] = branches;

        Shopware.Store.get('adminMenu').clearExpandedMenuEntries();
        wrapper.vm.activeBranchKey = null;

        wrapper.vm.$route.name = branchA.children[0].path;
        wrapper.vm.expandAncestorBranchesForCurrentRoute();

        expect(wrapper.vm.isNavigationEntryExpanded(branchA)).toBe(true);

        wrapper.vm.$route.name = branchB.children[0].path;
        wrapper.vm.expandAncestorBranchesForCurrentRoute();

        expect(wrapper.vm.isNavigationEntryExpanded(branchA)).toBe(false);
        expect(wrapper.vm.isNavigationEntryExpanded(branchB)).toBe(true);
    });

    it('should re-expand the branch owning the active route after it was collapsed manually', async () => {
        const branches = wrapper.vm.mainMenuEntries.filter((entry) => (entry.children?.length ?? 0) > 0);

        const [
            branchA,
            branchB,
        ] = branches;

        Shopware.Store.get('adminMenu').clearExpandedMenuEntries();
        wrapper.vm.activeBranchKey = null;

        wrapper.vm.$route.name = branchA.children[0].path;
        wrapper.vm.expandAncestorBranchesForCurrentRoute();

        expect(wrapper.vm.isNavigationEntryExpanded(branchA)).toBe(true);

        wrapper.vm.onMenuBranchToggle({ entry: branchA, open: false });
        wrapper.vm.onMenuBranchToggle({ entry: branchB, open: true });

        wrapper.vm.$route.name = branchA.children[0].path;
        wrapper.vm.expandAncestorBranchesForCurrentRoute();

        expect(wrapper.vm.isNavigationEntryExpanded(branchA)).toBe(true);
        expect(wrapper.vm.isNavigationEntryExpanded(branchB)).toBe(false);
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

    it('should suppress the menu transitions while the viewport is resizing', async () => {
        jest.useFakeTimers();

        wrapper.vm.onViewportResize();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('aside.sw-admin-menu').classes()).toContain('is--viewport-resizing');

        jest.advanceTimersByTime(200);
        await wrapper.vm.$nextTick();

        expect(wrapper.find('aside.sw-admin-menu').classes()).not.toContain('is--viewport-resizing');

        jest.useRealTimers();
    });

    it('should keep the closed off-canvas menu out of the tab order via inert', async () => {
        const menu = wrapper.find('aside.sw-admin-menu');
        // Browsers reflect `inert` as a property, jsdom only knows the attribute.
        const isInert = () => menu.element.inert === true || menu.element.getAttribute('inert') === 'true';

        // desktop: never inert
        expect(isInert()).toBe(false);

        wrapper.vm.viewportWidth = 1280;
        await wrapper.vm.$nextTick();

        expect(isInert()).toBe(true);

        wrapper.vm.onToggleCanvas(true);
        await wrapper.vm.$nextTick();

        expect(isInert()).toBe(false);
    });

    it('should trap the focus inside the off-canvas menu while it is open', async () => {
        const attachedWrapper = await createWrapper({ attachTo: document.body });
        attachedWrapper.vm.viewportWidth = 1280;

        attachedWrapper.vm.onToggleCanvas(true);
        await flushPromises();

        expect(attachedWrapper.vm.offCanvasFocusTrap).toBeTruthy();

        attachedWrapper.vm.onToggleCanvas(false);
        await flushPromises();

        expect(attachedWrapper.vm.offCanvasFocusTrap).toBeNull();

        attachedWrapper.unmount();
    });

    it('should keep the off-canvas menu open when an outside click dismisses an open menu dropdown', async () => {
        const attachedWrapper = await createWrapper({ attachTo: document.body });
        attachedWrapper.vm.viewportWidth = 1280;

        attachedWrapper.vm.onToggleCanvas(true);
        await flushPromises();

        // an open dropdown is only visible through its trigger state, the content is teleported away
        const dropdownTrigger = document.createElement('button');
        dropdownTrigger.setAttribute('data-state', 'open');
        dropdownTrigger.setAttribute('aria-haspopup', 'menu');
        attachedWrapper.vm.$refs.swAdminMenu.appendChild(dropdownTrigger);

        document.body.dispatchEvent(new MouseEvent('pointerdown', { bubbles: true }));
        document.body.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
        document.body.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await flushPromises();

        expect(attachedWrapper.vm.offCanvasFocusTrap).toBeTruthy();
        expect(attachedWrapper.vm.isOffCanvasShown).toBe(true);

        dropdownTrigger.remove();
        attachedWrapper.unmount();
    });

    it('should close the off-canvas menu on outside click without an open menu dropdown', async () => {
        const attachedWrapper = await createWrapper({ attachTo: document.body });
        attachedWrapper.vm.viewportWidth = 1280;

        attachedWrapper.vm.onToggleCanvas(true);
        await flushPromises();

        await attachedWrapper.find('.sw-admin-menu__backdrop').trigger('click');
        await flushPromises();

        expect(attachedWrapper.vm.offCanvasFocusTrap).toBeNull();

        attachedWrapper.unmount();
    });

    it('should return the focus to the opener when the off-canvas menu is dismissed explicitly', async () => {
        const opener = document.createElement('button');
        document.body.appendChild(opener);

        const attachedWrapper = await createWrapper({ attachTo: document.body });
        attachedWrapper.vm.viewportWidth = 1280;

        opener.focus();
        attachedWrapper.vm.onToggleCanvas(true);
        await flushPromises();

        expect(attachedWrapper.vm.offCanvasFocusTrap).toBeTruthy();

        // jsdom skips the trap's initial focus
        attachedWrapper.vm.$refs.swAdminMenu.querySelector('.sw-admin-menu__navigation-link').focus();

        await attachedWrapper.find('.sw-admin-menu__off-canvas-close').trigger('click');
        await flushPromises();
        // focus-trap returns the focus in a deferred tick
        await new Promise((resolve) => {
            window.setTimeout(resolve);
        });

        expect(attachedWrapper.vm.offCanvasFocusTrap).toBeNull();
        expect(attachedWrapper.vm.isOffCanvasShown).toBe(false);
        expect(document.activeElement).toBe(opener);

        opener.remove();
        attachedWrapper.unmount();
    });

    it('should not move the focus back when the off-canvas menu closes contextually', async () => {
        const opener = document.createElement('button');
        document.body.appendChild(opener);

        const attachedWrapper = await createWrapper({ attachTo: document.body });
        attachedWrapper.vm.viewportWidth = 1280;

        opener.focus();
        attachedWrapper.vm.onToggleCanvas(true);
        await flushPromises();

        // jsdom skips the trap's initial focus
        const panelLink = attachedWrapper.vm.$refs.swAdminMenu.querySelector('.sw-admin-menu__navigation-link');
        panelLink.focus();

        attachedWrapper.vm.closeOffCanvas();
        await flushPromises();
        await new Promise((resolve) => {
            window.setTimeout(resolve);
        });

        expect(attachedWrapper.vm.offCanvasFocusTrap).toBeNull();
        expect(attachedWrapper.vm.isOffCanvasShown).toBe(false);
        expect(document.activeElement).toBe(panelLink);

        opener.remove();
        attachedWrapper.unmount();
    });

    it('should keep the off-canvas menu open when Escape closes an open menu dropdown', async () => {
        const attachedWrapper = await createWrapper({ attachTo: document.body });
        attachedWrapper.vm.viewportWidth = 1280;

        attachedWrapper.vm.onToggleCanvas(true);
        await flushPromises();

        const dropdownTrigger = document.createElement('button');
        dropdownTrigger.setAttribute('data-state', 'open');
        dropdownTrigger.setAttribute('aria-haspopup', 'menu');
        attachedWrapper.vm.$refs.swAdminMenu.appendChild(dropdownTrigger);
        await flushPromises();

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await flushPromises();

        expect(attachedWrapper.vm.offCanvasFocusTrap).toBeTruthy();

        dropdownTrigger.remove();
        await flushPromises();

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await flushPromises();

        expect(attachedWrapper.vm.offCanvasFocusTrap).toBeNull();

        attachedWrapper.unmount();
    });

    it('should close the open off-canvas menu when the viewport grows past the breakpoint', async () => {
        wrapper.vm.viewportWidth = 1280;
        wrapper.vm.onToggleCanvas(true);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isOffCanvasShown).toBe(true);

        wrapper.vm.viewportWidth = 1920;
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isOffCanvasShown).toBe(false);
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
        it('expands the branch owning the active app after app modules are loaded', async () => {
            const adminMenuStore = Shopware.Store.get('adminMenu');

            adminMenuStore.expandSidebar();
            adminMenuStore.clearExpandedMenuEntries();
            wrapper.vm.activeBranchKey = null;
            wrapper.vm.$route.name = 'sw.extension.module';
            wrapper.vm.$route.matched = [{ name: 'sw.extension.module' }];
            wrapper.vm.$route.params = {
                appName: 'testAppB',
                moduleName: 'default',
            };
            wrapper.vm.appModulesService.fetchAppModules = () => Promise.resolve(testApps);

            await wrapper.vm.refreshApps();
            await flushPromises();

            const activeBranch = wrapper.vm.mainMenuEntries.find((entry) => entry.id === 'sw.first.top.level');

            expect(wrapper.vm.isNavigationEntryExpanded(activeBranch)).toBe(true);
        });

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

    describe('collapsed flyout keyboard access', () => {
        async function createCollapsedWrapper() {
            wrapper.unmount();
            wrapper = await createWrapper({ attachTo: document.body });
            await flushPromises();

            Shopware.Store.get('adminMenu').collapseSidebar();
            await flushPromises();

            return wrapper.find('.navigation-list-item__has-children');
        }

        afterEach(() => {
            wrapper.unmount();
        });

        it('should not open the flyout when a collapsed entry only receives focus', async () => {
            const target = await createCollapsedWrapper();

            await target.trigger('focusin');
            await flushPromises();

            expect(wrapper.vm.flyoutEntries).toHaveLength(0);
        });

        it('should open the flyout and move focus into it on ArrowRight', async () => {
            const target = await createCollapsedWrapper();

            target.find('.sw-admin-menu__navigation-link').element.focus();
            await target.trigger('keydown', { key: 'ArrowRight' });
            await flushPromises();

            expect(wrapper.vm.flyoutEntries.length).toBeGreaterThan(0);

            const flyout = document.getElementById('sw-admin-menu-flyout');
            expect(flyout).not.toBeNull();
            expect(flyout.contains(document.activeElement)).toBe(true);

            // The trigger acts as an expanded disclosure button while the flyout is open
            expect(target.find('.sw-admin-menu__navigation-link').attributes('aria-expanded')).toBe('true');
        });

        it('should close the flyout on Escape and return focus to the menu entry', async () => {
            const target = await createCollapsedWrapper();
            const trigger = target.find('.sw-admin-menu__navigation-link');

            trigger.element.focus();
            await target.trigger('keydown', { key: 'ArrowRight' });
            await flushPromises();

            const flyout = document.getElementById('sw-admin-menu-flyout');
            await new DOMWrapper(flyout).trigger('keydown', { key: 'Escape' });
            await flushPromises();
            // focus-trap returns the focus in a deferred tick
            await new Promise((resolve) => {
                window.setTimeout(resolve);
            });

            expect(wrapper.vm.flyoutEntries).toHaveLength(0);
            expect(document.activeElement).toBe(trigger.element);
        });

        it('should move focus through the navigation with arrow keys', async () => {
            await createCollapsedWrapper();

            const body = wrapper.find('.sw-admin-menu__body');
            const links = Array.from(body.element.querySelectorAll('.sw-admin-menu__navigation-link')).filter(
                (link) => !link.closest('[hidden]'),
            );
            expect(links.length).toBeGreaterThan(1);

            links[0].focus();
            await body.trigger('keydown', { key: 'ArrowDown' });
            expect(document.activeElement).toBe(links[1]);

            await body.trigger('keydown', { key: 'ArrowUp' });
            expect(document.activeElement).toBe(links[0]);

            await body.trigger('keydown', { key: 'End' });
            expect(document.activeElement).toBe(links[links.length - 1]);

            await body.trigger('keydown', { key: 'Home' });
            expect(document.activeElement).toBe(links[0]);
        });

        it('should move focus through the flyout with arrow keys and close it with ArrowLeft', async () => {
            const target = await createCollapsedWrapper();
            const trigger = target.find('.sw-admin-menu__navigation-link');

            trigger.element.focus();
            await target.trigger('keydown', { key: 'ArrowRight' });
            await flushPromises();

            const flyout = document.getElementById('sw-admin-menu-flyout');
            const flyoutWrapper = new DOMWrapper(flyout);
            const links = flyout.querySelectorAll('.sw-admin-menu__navigation-link');
            expect(links.length).toBeGreaterThan(1);
            // In jsdom the focus trap falls back to the container itself
            expect(flyout.contains(document.activeElement)).toBe(true);

            await flyoutWrapper.trigger('keydown', { key: 'ArrowDown' });
            expect(document.activeElement).toBe(links[0]);

            await flyoutWrapper.trigger('keydown', { key: 'ArrowDown' });
            expect(document.activeElement).toBe(links[1]);

            await flyoutWrapper.trigger('keydown', { key: 'ArrowUp' });
            expect(document.activeElement).toBe(links[0]);

            await flyoutWrapper.trigger('keydown', { key: 'ArrowLeft' });
            await flushPromises();
            // focus-trap returns the focus in a deferred tick
            await new Promise((resolve) => {
                window.setTimeout(resolve);
            });

            expect(wrapper.vm.flyoutEntries).toHaveLength(0);
            expect(document.activeElement).toBe(trigger.element);
        });

        it('should skip links of closed sub branches when moving focus with arrow keys', async () => {
            const target = await createCollapsedWrapper();

            target.find('.sw-admin-menu__navigation-link').element.focus();
            await target.trigger('keydown', { key: 'ArrowRight' });
            await flushPromises();

            const flyout = document.getElementById('sw-admin-menu-flyout');

            // The closed sub branch children stay in the DOM as hidden, non-focusable links
            const allLinks = Array.from(flyout.querySelectorAll('.sw-admin-menu__navigation-link'));
            const visibleLinks = allLinks.filter((link) => !link.closest('[hidden]'));
            expect(visibleLinks.length).toBeLessThan(allLinks.length);

            await new DOMWrapper(flyout).trigger('keydown', { key: 'End' });
            expect(document.activeElement).toBe(visibleLinks[visibleLinks.length - 1]);

            await new DOMWrapper(flyout).trigger('keydown', { key: 'ArrowDown' });
            expect(document.activeElement).toBe(visibleLinks[0]);
        });

        it('should close the flyout on route change without pulling focus back', async () => {
            const target = await createCollapsedWrapper();

            target.find('.sw-admin-menu__navigation-link').element.focus();
            await target.trigger('keydown', { key: 'ArrowRight' });
            await flushPromises();

            expect(wrapper.vm.flyoutEntries.length).toBeGreaterThan(0);

            wrapper.vm.$options.watch['$route.path'].handler.call(wrapper.vm);
            await flushPromises();

            expect(wrapper.vm.flyoutEntries).toHaveLength(0);
            expect(target.find('.sw-admin-menu__navigation-link').element).not.toBe(document.activeElement);
        });
    });

    describe('collapsed flyout branch navigation', () => {
        async function openFlyout() {
            wrapper.unmount();
            wrapper = await createWrapper({ attachTo: document.body });
            await flushPromises();

            Shopware.Store.get('adminMenu').collapseSidebar();
            await flushPromises();

            await wrapper.find('.navigation-list-item__has-children').trigger('mouseenter');
            await flushPromises();

            return document.getElementById('sw-admin-menu-flyout');
        }

        function findRow(flyout, hasChildren) {
            const selector = hasChildren
                ? '.sw-admin-menu__navigation-list-item.navigation-list-item__has-children'
                : '.sw-admin-menu__navigation-list-item:not(.navigation-list-item__has-children)';

            const row = flyout.querySelector(selector);
            expect(row).not.toBeNull();

            return row;
        }

        afterEach(() => {
            wrapper.unmount();
        });

        it('should keep the flyout open when a row that is a collapsible and a route navigates', async () => {
            const flyout = await openFlyout();
            const branchRow = findRow(flyout, true);

            branchRow.querySelector('.sw-admin-menu__navigation-link').click();

            // Synchronous on purpose: the suppression must be armed by the time the click returns,
            // a handler seeing only the bubbled event runs after the flyout already closed
            expect(wrapper.vm.isFlyoutPinned).toBe(true);

            wrapper.vm.$options.watch['$route.path'].handler.call(wrapper.vm);
            await flushPromises();

            expect(wrapper.vm.flyoutEntries.length).toBeGreaterThan(0);
        });

        it('should close the flyout when a leaf row navigates', async () => {
            const flyout = await openFlyout();
            const leafRow = findRow(flyout, false);

            leafRow.querySelector('.sw-admin-menu__navigation-link').click();

            expect(wrapper.vm.isFlyoutPinned).toBe(false);

            wrapper.vm.$options.watch['$route.path'].handler.call(wrapper.vm);
            await flushPromises();

            expect(wrapper.vm.flyoutEntries).toHaveLength(0);
        });

        it('should keep the flyout open across follow-up route changes while pinned', async () => {
            const flyout = await openFlyout();

            findRow(flyout, true).querySelector('.sw-admin-menu__navigation-link').click();

            // List pages replace the route again later, so the pin is state, not a time window
            for (let i = 0; i < 3; i += 1) {
                wrapper.vm.$options.watch['$route.path'].handler.call(wrapper.vm);
                await flushPromises();
            }

            expect(wrapper.vm.flyoutEntries.length).toBeGreaterThan(0);
        });

        it('should release the pin when a leaf inside the pinned flyout navigates', async () => {
            const flyout = await openFlyout();

            findRow(flyout, true).querySelector('.sw-admin-menu__navigation-link').click();
            expect(wrapper.vm.isFlyoutPinned).toBe(true);

            findRow(flyout, false).querySelector('.sw-admin-menu__navigation-link').click();
            expect(wrapper.vm.isFlyoutPinned).toBe(false);

            wrapper.vm.$options.watch['$route.path'].handler.call(wrapper.vm);
            await flushPromises();

            expect(wrapper.vm.flyoutEntries).toHaveLength(0);
        });

        it('should ignore the focusout caused by a branch navigation but still close on pointer leave', async () => {
            const flyout = await openFlyout();
            const flyoutWrapper = new DOMWrapper(flyout);

            findRow(flyout, true).querySelector('.sw-admin-menu__navigation-link').click();

            // Navigating fires a focusout with no relatedTarget, which must not schedule a close
            await flyoutWrapper.trigger('focusout');
            expect(wrapper.vm.flyoutCloseTimeoutId).toBeNull();

            // Leaving with the pointer is a real intent to dismiss and still closes.
            await flyoutWrapper.trigger('mouseleave');
            expect(wrapper.vm.flyoutCloseTimeoutId).not.toBeNull();
        });
    });

    it('should not show icons in flyout menu items', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        // Flyouts only open while the sidebar is collapsed.
        Shopware.Store.get('adminMenu').collapseSidebar();
        await flushPromises();

        const target = wrapper.find('.navigation-list-item__has-children');
        await target.trigger('mouseenter');
        await flushPromises();

        expect(wrapper.vm.flyoutEntries.length).toBeGreaterThan(0);

        const flyoutItem = wrapper.find('.sw-admin-menu__flyout-list .sw-admin-menu__navigation-link');
        expect(flyoutItem.exists()).toBe(true);
        expect(flyoutItem.element.querySelectorAll('mt-icon-stub, .mt-icon')).toHaveLength(0);
    });

    it('should close the off-canvas menu on route change on mobile', async () => {
        const emitSpy = jest.spyOn(Shopware.Utils.EventBus, 'emit');

        wrapper.vm.viewportWidth = 375;
        wrapper.vm.isOffCanvasShown = true;

        wrapper.vm.$options.watch['$route.path'].handler.call(wrapper.vm);
        await flushPromises();

        expect(wrapper.vm.isOffCanvasShown).toBe(false);
        expect(emitSpy).toHaveBeenCalledWith('sw-admin-menu/toggle-offcanvas', false);

        emitSpy.mockRestore();
    });

    it('should not close the off-canvas menu on route change on desktop', async () => {
        wrapper.vm.viewportWidth = 1920;
        wrapper.vm.isOffCanvasShown = true;

        wrapper.vm.$options.watch['$route.path'].handler.call(wrapper.vm);
        await flushPromises();

        expect(wrapper.vm.isOffCanvasShown).toBe(true);
    });

    // Tapping the entry of the current route aborts as redundant navigation — no route change fires
    it('should close the off-canvas menu when a navigation link is clicked on mobile', async () => {
        wrapper.vm.viewportWidth = 375;
        wrapper.vm.isOffCanvasShown = true;
        await flushPromises();

        const link = wrapper.find('a.sw-admin-menu__navigation-link');
        expect(link.exists()).toBe(true);

        await link.trigger('click');
        await flushPromises();

        expect(wrapper.vm.isOffCanvasShown).toBe(false);
    });

    it('should close the user actions menu when the viewport switches to off-canvas mode', async () => {
        wrapper.vm.viewportWidth = 1920;
        await flushPromises();

        wrapper.vm.isUserActionsActive = true;

        wrapper.vm.viewportWidth = 375;
        await flushPromises();

        expect(wrapper.vm.isUserActionsActive).toBe(false);
    });

    it('should close the user actions menu on route change', async () => {
        wrapper.vm.isUserActionsActive = true;

        wrapper.vm.$options.watch['$route.path'].handler.call(wrapper.vm);
        await flushPromises();

        expect(wrapper.vm.isUserActionsActive).toBe(false);
    });

    it('should provide an accessible name for the user actions toggle', async () => {
        Shopware.Store.get('session').setCurrentUser({
            firstName: 'Max',
            lastName: 'Mustermann',
            admin: true,
        });
        await flushPromises();

        const toggle = wrapper.find('.sw-admin-menu__user-actions-toggle');
        expect(toggle.attributes('aria-label')).toBe('Max Mustermann, global.sw-admin-menu.administrator');
    });
});
