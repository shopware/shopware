import { mount } from '@vue/test-utils';

const module = {
    heading: 'jest',
    locationId: 'jest',
    displaySearchBar: true,
    displayLanguageSwitch: true,
    baseUrl: 'http://example.com',
};

async function createWrapper(back = null, push = jest.fn()) {
    return mount(await wrapTestComponent('sw-extension-sdk-module', { sync: true }), {
        props: {
            id: Shopware.Utils.format.md5(JSON.stringify(module)),
            back,
        },
        global: {
            stubs: {
                'sw-page': await wrapTestComponent('sw-page'),
                'sw-loader': true,
                'sw-my-apps-error-page': true,
                'sw-iframe-renderer': true,
                'sw-language-switch': true,
                'sw-button': await wrapTestComponent('sw-button'),
                'router-link': {
                    props: {
                        to: { type: String, required: true },
                    },
                    template: '<a @click="$router.push(to)"></a>',
                },
            },
            mocks: {
                $route: {
                    meta: {
                        $module: {},
                    },
                },
                $router: {
                    push,
                },
            },
        },
    });
}

jest.setTimeout(8000);

describe('src/module/sw-extension-sdk/page/sw-extension-sdk-module', () => {
    /** @type Wrapper */
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('@slow should time out without menu item after 7000ms', async () => {
        await new Promise((r) => {
            setTimeout(r, 7100);
        });
        expect(wrapper.vm.timedOut).toBe(true);
    });

    it('@slow should not time out with menu item', async () => {
        const moduleId = await Shopware.State.dispatch('extensionSdkModules/addModule', module);
        expect(typeof moduleId).toBe('string');
        expect(moduleId).toBe(wrapper.vm.id);

        await new Promise((r) => {
            setTimeout(r, 7100);
        });
        expect(wrapper.vm.timedOut).toBe(false);
    });

    it('should show language switch', async () => {
        await Shopware.State.dispatch('extensionSdkModules/addModule', module);

        expect(wrapper.findComponent('sw-language-switch-stub').exists()).toBe(true);
    });

    it('should show smart bar button', async () => {
        const spy = jest.fn();

        await Shopware.State.dispatch('extensionSdkModules/addModule', module);
        Shopware.State.commit('extensionSdkModules/addSmartBarButton', {
            locationId: 'jest',
            buttonId: 'test-button-1',
            label: 'Test button 1',
            variant: 'primary',
            onClickCallback: () => spy(),
        });

        await wrapper.vm.$nextTick();

        const smartBarButton = wrapper.find('button');

        expect(smartBarButton.exists()).toBe(true);

        expect(smartBarButton.text()).toBe('Test button 1');
        expect(smartBarButton.attributes().id).toBe('test-button-1');
        expect(smartBarButton.classes('sw-button--primary')).toBe(true);

        // Test if callback function is called
        await smartBarButton.trigger('click');
        expect(spy).toHaveBeenCalledTimes(1);
    });

    it('should display back button', async () => {
        wrapper.unmount();

        const back = 'sw.settings.index.plugins';
        const routerPush = jest.fn();
        wrapper = await createWrapper(back, routerPush);

        await wrapper.find('.sw-page__back-btn-container a').trigger('click');
        expect(routerPush).toHaveBeenCalledWith({ name: back });
    });
});
