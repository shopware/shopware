/**
 * @sw-package buyers-experience
 */
import { h } from 'vue';
import { config, mount } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';

function translateSnippet(key) {
    const snippets = {
        'sw-shortcut-overview.title': 'Keyboard shortcuts',
        'sw-shortcut-overview.keyboardShortcutSpecialShortcutShortcutListing': 'Shift-?',
    };

    return snippets[key] ?? key;
}

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
            plugins: [router],
            stubs: {
                'sw-extension-component-section': true,
                'sw-external-link': true,
                'sw-loader': true,
                'mt-tooltip': {
                    props: {
                        content: {
                            type: String,
                            required: true,
                        },
                        placement: {
                            type: String,
                            required: true,
                        },
                    },
                    setup(props, { slots }) {
                        return () =>
                            h(
                                'div',
                                {
                                    class: 'mt-tooltip-stub',
                                    'data-content': props.content,
                                    'data-placement': props.placement,
                                },
                                slots.default?.({
                                    id: 'mt-tooltip-stub-trigger',
                                    onFocus: () => {},
                                    onBlur: () => {},
                                    onKeydown: () => {},
                                    onMouseover: () => {},
                                    onMouseleave: () => {},
                                    onMousedown: () => {},
                                    onMouseup: () => {},
                                    'aria-describedby': 'mt-tooltip-stub-content',
                                }),
                            );
                    },
                },
            },
            mocks: {
                $t: translateSnippet,
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
        const tooltip = wrapper.find('.mt-tooltip-stub');
        const shortcutButton = wrapper.find('.sw-help-sidebar__shortcut-button');
        const tooltipContent = tooltip.attributes('data-content');

        expect(tooltip.attributes('data-placement')).toBe('top');
        expect(tooltipContent).toContain('sw-help-sidebar__tooltip-title');
        expect(tooltipContent).toContain('Keyboard shortcuts');
        expect(tooltipContent).toContain('sw-help-sidebar__tooltip-shortcut-key');
        expect(tooltipContent).toContain('?');
        expect(tooltipContent).toContain('aria-label="Shift"');
        expect(tooltipContent).toContain('aria-label="?"');
        expect(tooltipContent).toMatch(/Shift|⇧/);
        expect(shortcutButton.attributes('aria-describedby')).toBe('mt-tooltip-stub-content');
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
