/**
 * @sw-package framework
 */

import { config } from '@vue/test-utils';
import createWrapper, { registerAdminModules } from './create-wrapper';

describe('src/app/component/structure/sw-admin-menu: branch expansion', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.Store.get('session').currentLocale = 'en-GB';
        Shopware.Context.app.fallbackLocale = 'en-GB';

        registerAdminModules();

        // The shared fixture has no childless top level entry, and Dashboard/Settings are exactly that
        Shopware.Module.register('childless-module', {
            type: 'core',
            icon: 'default-icon',
            routes: { index: { path: 'index', component: 'sw-index' } },
            navigation: [
                {
                    id: 'sw.childless.top.level',
                    path: 'childless.module.index',
                    label: 'childless top level entry',
                    icon: 'default-icon',
                    position: 90,
                },
            ],
        });
    });

    beforeEach(async () => {
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

    function openBranchOnItsOwnRoute() {
        const branch = wrapper.vm.mainMenuEntries.find((entry) => (entry.children?.length ?? 0) > 0);

        Shopware.Store.get('adminMenu').clearExpandedMenuEntries();
        wrapper.vm.activeBranchKey = null;

        wrapper.vm.$route.name = branch.children[0].path;
        wrapper.vm.$route.matched = [{ name: branch.children[0].path }];
        wrapper.vm.expandAncestorBranchesForCurrentRoute();

        expect(wrapper.vm.isNavigationEntryExpanded(branch)).toBe(true);

        return branch;
    }

    it('should keep the branch open when a module route reaches its entry through the module manifest', async () => {
        const branch = openBranchOnItsOwnRoute();
        const menuPath = branch.children[0].path;

        // A detail route next to the menu target, declaring no parentPath
        wrapper.vm.$route.name = `${menuPath}.detail`;
        wrapper.vm.$route.matched = [{ name: `${menuPath}.detail` }];
        wrapper.vm.$route.meta = { $module: { navigation: [{ path: menuPath }] } };
        wrapper.vm.expandAncestorBranchesForCurrentRoute();

        expect(wrapper.vm.isNavigationEntryExpanded(branch)).toBe(true);
        expect(wrapper.vm.activeBranchKey).toBe(branch.id ?? branch.path);
    });

    it('should leave the tree untouched on a route the menu does not list', async () => {
        const branch = openBranchOnItsOwnRoute();
        const branchKey = wrapper.vm.activeBranchKey;

        // Sales channels, the profile page and the privilege error page all land here
        wrapper.vm.$route.name = 'sw.profile.index';
        wrapper.vm.$route.matched = [{ name: 'sw.profile.index' }];
        wrapper.vm.expandAncestorBranchesForCurrentRoute();

        expect(wrapper.vm.isNavigationEntryExpanded(branch)).toBe(true);
        expect(wrapper.vm.activeBranchKey).toBe(branchKey);
    });

    it('should still close the tree for a childless top level entry', async () => {
        const branch = openBranchOnItsOwnRoute();
        const childless = wrapper.vm.mainMenuEntries.find((entry) => entry.id === 'sw.childless.top.level');

        expect(childless.children).toHaveLength(0);

        wrapper.vm.$route.name = childless.path;
        wrapper.vm.$route.matched = [{ name: childless.path }];
        wrapper.vm.expandAncestorBranchesForCurrentRoute();

        expect(wrapper.vm.isNavigationEntryExpanded(branch)).toBe(false);
        expect(wrapper.vm.activeBranchKey).toBeNull();
    });
});
