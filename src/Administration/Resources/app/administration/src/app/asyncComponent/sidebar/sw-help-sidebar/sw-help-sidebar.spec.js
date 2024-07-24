/**
 * @package buyers-experience
 * @group disabledCompat
 */
import { config, mount } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';
import { MtButton } from '@shopware-ag/meteor-component-library';

async function createWrapper() {
    delete config.global.mocks.$router;
    delete config.global.mocks.$route;

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
                'sw-icon': true,
                'sw-external-link': true,
                'sw-button': await wrapTestComponent('sw-button', { sync: true }),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                'mt-button': MtButton,
                'sw-loader': true,
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
