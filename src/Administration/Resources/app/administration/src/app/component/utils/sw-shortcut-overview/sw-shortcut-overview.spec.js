import { mount } from '@vue/test-utils';

/**
 * @package admin
 */

describe('app/component/utils/sw-shortcut-overview', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = mount(await wrapTestComponent('sw-shortcut-overview', { sync: true }), {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'sw-modal': true,
                    'sw-shortcut-overview-item': true,
                },
            },
        });
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should add the privilege attribute to some shortcut-overview-items', async () => {
        await wrapper.setData({
            showShortcutOverviewModal: true,
        });

        const privilegeSystemClearCacheItems = wrapper.findAll(
            'sw-shortcut-overview-item-stub[privilege="system.clear_cache"]',
        );
        const privilegeSystemPluginMaintainItems = wrapper.findAll(
            'sw-shortcut-overview-item-stub[privilege="system.plugin_maintain"]',
        );

        expect(privilegeSystemClearCacheItems).toHaveLength(3);
        expect(privilegeSystemPluginMaintainItems).toHaveLength(1);
    });
});
