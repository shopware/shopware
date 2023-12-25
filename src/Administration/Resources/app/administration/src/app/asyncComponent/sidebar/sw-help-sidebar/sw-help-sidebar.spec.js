/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-help-sidebar', { sync: true }), {
        global: {
            stubs: {
                'sw-extension-component-section': true,
            },
            provide: {
                shortcutService: {
                    startEventListener: () => {},
                    stopEventListener: () => {},
                },
            },
        },
        attachTo: document.body,
    });
}

describe('src/app/asyncComponent/sidebar/sw-help-sidebar', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        Shopware.State.commit('adminHelpCenter/setShowHelpSidebar', true);
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to open the help sidebar', async () => {
        expect(wrapper.find('.sw-help-sidebar').exists()).toBeTruthy();

        Shopware.State.commit('adminHelpCenter/setShowHelpSidebar', false);
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-help-sidebar').exists()).toBeFalsy();
    });

    it('should be able to close the help sidebar', async () => {
        expect(wrapper.find('.sw-help-sidebar').exists()).toBeTruthy();

        await wrapper.find('.sw-help-sidebar__button-close').trigger('click');

        expect(wrapper.find('.sw-help-sidebar').exists()).toBeFalsy();
    });

    it('should be able to open the shortcut modal', async () => {
        expect(wrapper.find('.sw-help-sidebar').exists()).toBeTruthy();

        await wrapper.find('.sw-help-sidebar__shortcut-button').trigger('click');

        expect(Shopware.State.get('adminHelpCenter').showShortcutModal).toBeTruthy();
    });

    it('should close the sidebar if the user clicks outside of the sidebar', async () => {
        expect(wrapper.find('.sw-help-sidebar').exists()).toBeTruthy();

        await wrapper.get('.sw-help-sidebar').trigger('mousedown');

        expect(wrapper.find('.sw-help-sidebar').exists()).toBeFalsy();
    });

    it('should closes the sidebar if the user presses the escape key', async () => {
        expect(wrapper.find('.sw-help-sidebar').exists()).toBeTruthy();

        const helpSidebarContainer = wrapper.find('.sw-help-sidebar__container');
        Object.defineProperty(document, 'activeElement', {
            value: wrapper.find('.sw-help-sidebar__container').element,
            writable: false,
        });

        await helpSidebarContainer.trigger('focus');
        await helpSidebarContainer.trigger('keyup.Escape');

        expect(wrapper.find('.sw-help-sidebar').exists()).toBeFalsy();
    });
});
