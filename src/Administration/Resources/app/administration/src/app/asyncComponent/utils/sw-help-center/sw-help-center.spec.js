/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-help-center-v2', { sync: true }), {
        global: {
            stubs: {
                'sw-button': await wrapTestComponent('sw-button', { sync: true }),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                'sw-help-sidebar': true,
                'sw-shortcut-overview': true,
                'sw-extension-component-section': true,
            },
        },
    });
}

describe('src/app/asyncComponent/utils/sw-help-center', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to open the help sidebar', async () => {
        await wrapper.find('.sw-help-center__button').trigger('click');

        expect(wrapper.find('sw-help-sidebar-stub').exists()).toBeTruthy();
    });

    it('should be able to close the help sidebar', async () => {
        await wrapper.find('.sw-help-center__button').trigger('click');

        expect(wrapper.find('sw-help-sidebar-stub').exists()).toBeTruthy();

        Shopware.State.commit('adminHelpCenter/setShowHelpSidebar', false);
        await wrapper.vm.$nextTick();

        expect(wrapper.find('sw-help-sidebar-stub').exists()).toBeFalsy();
    });

    it('should be able to toggle the shortcut overview', async () => {
        wrapper.vm.$refs.shortcutModal.onOpenShortcutOverviewModal = jest.fn();

        await wrapper.find('.sw-help-center__button').trigger('click');
        expect(wrapper.find('sw-help-sidebar-stub').exists()).toBeTruthy();
        wrapper.vm.$refs.helpSidebar.setFocusToSidebar = jest.fn();

        Shopware.State.commit('adminHelpCenter/setShowShortcutModal', true);
        await wrapper.vm.$nextTick();
        expect(wrapper.find('sw-shortcut-overview-stub').exists()).toBeTruthy();
        expect(wrapper.vm.$refs.shortcutModal.onOpenShortcutOverviewModal).toHaveBeenCalled();

        Shopware.State.commit('adminHelpCenter/setShowShortcutModal', false);
        await wrapper.vm.$nextTick();
        expect(wrapper.find('sw-shortcut-overview-stub').exists()).toBeTruthy();
        expect(wrapper.vm.$refs.helpSidebar.setFocusToSidebar).toHaveBeenCalled();
    });
});
