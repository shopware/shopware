import { shallowMount } from '@vue/test-utils';
import 'src/app/component/utils/sw-shortcut-overview';

describe('app/component/utils/sw-shortcut-overview', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-shortcut-overview'), {
            stubs: {
                'sw-modal': true,
                'sw-shortcut-overview-item': true
            }
        });
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should add the privilege attribute to some shortcut-overview-items', async () => {
        await wrapper.setData({
            showShortcutOverviewModal: true
        });

        const privilegeSystemClearCacheItems = wrapper.findAll(
            'sw-shortcut-overview-item-stub[privilege="system.clear_cache"]'
        );
        const privilegeSystemPluginMaintainItems = wrapper.findAll(
            'sw-shortcut-overview-item-stub[privilege="system.plugin_maintain"]'
        );

        expect(privilegeSystemClearCacheItems.length).toBe(3);
        expect(privilegeSystemPluginMaintainItems.length).toBe(1);
    });
});
