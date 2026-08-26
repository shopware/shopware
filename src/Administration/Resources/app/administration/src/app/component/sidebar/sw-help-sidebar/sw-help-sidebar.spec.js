/**
 * @sw-package buyers-experience
 */
import { config, mount } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';

async function createWrapper(viewportWidth = 1920) {
    delete config.global.mocks.$router;
    delete config.global.mocks.$route;
    Object.defineProperty(window, 'innerWidth', {
        configurable: true,
        writable: true,
        value: viewportWidth,
    });

    const router = createRouter({
        history: createWebHashHistory(),
        routes: [
            {
                path: '/',
                name: 'sw.dashboard.index',
                component: {
                    template: 'dashboard',
                },
            },
            {
                path: '/sw/settings/index',
                name: 'sw.settings.index',
                component: {
                    template: 'settings',
                },
            },
        ],
    });
    router.push({ name: 'sw.dashboard.index' });

    return mount(await wrapTestComponent('sw-help-sidebar', { sync: true }), {
        global: {
            renderStubDefaultSlot: true,
            plugins: [router],
            stubs: {
                'sw-extension-component-section': true,
                'sw-external-link': true,
                'sw-loader': true,
                'mt-tooltip': true,
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
        Shopware.Store.get('adminHelpCenter').showHelpSidebar = true;
        Shopware.Store.get('adminHelpCenter').showShortcutModal = false;
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
    });

    it('should be able to open the help sidebar', async () => {
        expect(wrapper.find('.sw-help-sidebar').exists()).toBeTruthy();

        Shopware.Store.get('adminHelpCenter').showHelpSidebar = false;
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-help-sidebar').exists()).toBeFalsy();
    });

    it('should be able to close the help sidebar', async () => {
        expect(wrapper.find('.sw-help-sidebar').exists()).toBeTruthy();

        await wrapper.find('.sw-help-sidebar__button-close').trigger('click');

        expect(wrapper.find('.sw-help-sidebar').exists()).toBeFalsy();
    });

    it('should be able to open the shortcut modal on tablet viewports', async () => {
        wrapper.unmount();
        wrapper = await createWrapper(768);

        expect(wrapper.find('.sw-help-sidebar').exists()).toBeTruthy();

        await wrapper.find('.sw-help-sidebar__shortcut-button').trigger('click');

        expect(Shopware.Store.get('adminHelpCenter').showShortcutModal).toBeTruthy();
    });

    it('should render the shortcut button with a Meteor tooltip above the button', async () => {
        const tooltipEl = wrapper.find('mt-tooltip-stub');
        expect(tooltipEl.attributes('placement')).toBe('top');
        expect(tooltipEl.attributes('content')).toContain('sw-help-sidebar__tooltip-title');
        expect(tooltipEl.attributes('content')).toContain('sw-shortcut-overview.title');
        expect(tooltipEl.attributes('content')).toContain('sw-help-sidebar__tooltip-shortcut-key');
    });

    it('should hide the shortcut button on mobile viewports', async () => {
        wrapper.unmount();
        wrapper = await createWrapper(500);

        expect(wrapper.find('.sw-help-sidebar').exists()).toBeTruthy();
        expect(wrapper.find('.sw-help-sidebar__shortcut-button').exists()).toBeFalsy();
    });

    it('should hide the shortcut button when the viewport is resized below the mobile breakpoint', async () => {
        expect(wrapper.find('.sw-help-sidebar__shortcut-button').exists()).toBe(true);

        window.innerWidth = 500;
        window.dispatchEvent(new Event('resize'));
        await flushPromises();

        expect(wrapper.find('.sw-help-sidebar__shortcut-button').exists()).toBe(false);
    });

    it('should close the sidebar if the user clicks outside of the sidebar', async () => {
        expect(wrapper.find('.sw-help-sidebar').exists()).toBeTruthy();

        await wrapper.get('.sw-help-sidebar').trigger('mousedown');

        expect(wrapper.find('.sw-help-sidebar').exists()).toBeFalsy();
    });

    it('should close the sidebar if the user presses the escape key', async () => {
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

    it('should close the sidebar if route changes', async () => {
        expect(wrapper.find('.sw-help-sidebar').exists()).toBeTruthy();

        await wrapper.vm.$router.push({ name: 'sw.settings.index' });

        expect(wrapper.find('.sw-help-sidebar').exists()).toBeFalsy();
    });
});
