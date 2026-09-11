/**
 * @sw-package framework
 */

import { config } from '@vue/test-utils';
import useModuleIconColors from 'src/app/composables/use-module-icon-colors';
import createWrapper, { registerAdminModules } from './create-wrapper';

describe('src/app/component/structure/sw-admin-menu: flyout module colors', () => {
    const moduleColor = '#57D9A3';

    let wrapper;

    function getHoveredEntry() {
        return Shopware.Module.getModuleRegistry()
            .get('first-module')
            .navigation.find((entry) => entry.id === 'sw.second.top.level');
    }

    async function openFlyout() {
        wrapper = await createWrapper({ attachTo: document.body });
        await flushPromises();

        Shopware.Store.get('adminMenu').collapseSidebar();
        await flushPromises();

        await wrapper.find('.navigation-list-item__sw-second-top-level').trigger('mouseenter');
        await flushPromises();

        return document.getElementById('sw-admin-menu-flyout');
    }

    beforeAll(() => {
        Shopware.Store.get('session').currentLocale = 'en-GB';
        Shopware.Context.app.fallbackLocale = 'en-GB';

        registerAdminModules();
    });

    beforeEach(() => {
        config.global.stubs = {
            ...config.global.stubs,
            transition: false,
        };

        jest.spyOn(Shopware.Utils.debug, 'error').mockImplementation(() => true);

        Shopware.Store.get('session').setCurrentUser(null);
        Shopware.Store.get('settingsItems').settingsGroups.shop = [];
        Shopware.Store.get('settingsItems').settingsGroups.system = [];
        Shopware.Store.get('shopwareApps').apps = [];

        getHoveredEntry().color = moduleColor;
    });

    afterEach(() => {
        delete getHoveredEntry().color;
        useModuleIconColors().enabled.value = false;
        wrapper.unmount();
    });

    it('should leave the flyout active state to the stylesheet by default', async () => {
        const flyout = await openFlyout();

        expect(wrapper.vm.flyoutModuleColor).toBeUndefined();
        expect(flyout.classList.contains('is--module-colored')).toBe(false);
        expect(flyout.getAttribute('style')).toBeNull();
    });

    it('should hand the module color of the hovered entry to the flyout when the preference is enabled', async () => {
        useModuleIconColors().enabled.value = true;

        const flyout = await openFlyout();

        expect(wrapper.vm.flyoutModuleColor).toBe(moduleColor);
        expect(flyout.classList.contains('is--module-colored')).toBe(true);
        expect(flyout.getAttribute('style')).toContain(`--sw-admin-menu-module-color: ${moduleColor}`);
    });

    it('should not mark the flyout as module colored for entries without a module color', async () => {
        useModuleIconColors().enabled.value = true;
        delete getHoveredEntry().color;

        const flyout = await openFlyout();

        expect(wrapper.vm.flyoutModuleColor).toBeUndefined();
        expect(flyout.classList.contains('is--module-colored')).toBe(false);
    });

    it('should drop the module color once the flyout closes', async () => {
        useModuleIconColors().enabled.value = true;

        await openFlyout();
        wrapper.vm.onFlyoutLeave();
        await flushPromises();

        expect(wrapper.vm.flyoutModuleColor).toBeUndefined();
    });
});
