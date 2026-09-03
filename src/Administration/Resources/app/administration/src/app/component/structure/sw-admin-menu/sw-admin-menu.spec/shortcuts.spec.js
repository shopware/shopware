/**
 * @sw-package framework
 */

import { config } from '@vue/test-utils';
import createWrapper, { registerAdminModules } from './create-wrapper';

describe('src/app/component/structure/sw-admin-menu: shortcuts', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.Store.get('session').currentLocale = 'en-GB';
        Shopware.Context.app.fallbackLocale = 'en-GB';

        registerAdminModules();
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

    it('should toggle the sidebar with the S shortcut on desktop viewports only', async () => {
        const shortcut = wrapper.vm.$options.shortcuts.S;

        expect(shortcut.method).toBe('onToggleSidebar');

        wrapper.vm.viewportWidth = 1920;
        expect(shortcut.active.call(wrapper.vm)).toBe(true);

        wrapper.vm.viewportWidth = 1280;
        expect(shortcut.active.call(wrapper.vm)).toBe(false);
    });
});
