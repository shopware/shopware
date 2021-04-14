import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-extension/page/sw-extension-store-landing-page';
import 'src/app/component/base/sw-button';

let successfulActivation = true;

function createWrapper() {
    const localVue = createLocalVue();
    localVue.filter('asset', v => v);

    return shallowMount(Shopware.Component.build('sw-extension-store-landing-page'), {
        localVue,
        stubs: {
            'sw-meteor-page': {
                template: '<div><slot name="content"></slot><slot name="smart-bar-actions"></slot></div>'
            },
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-icon': true,
            'sw-label': true
        },
        provide: {
            extensionHelperService: {
                downloadAndActivateExtension: () => {
                    if (successfulActivation) {
                        return Promise.resolve();
                    }

                    return Promise.reject();
                }
            }
        }
    });
}

describe('src/module/sw-extension/page/sw-extension-store-landing-page', () => {
    /** @type Wrapper */
    let wrapper;

    beforeAll(() => {
        delete window.location;
        window.location = { reload: jest.fn() };
        Shopware.Utils.debug.error = jest.fn();
    });

    beforeEach(async () => {
        successfulActivation = true;
        window.location.reload.mockClear();
        Shopware.Utils.debug.error.mockClear();
        wrapper = await createWrapper();
    });

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show the activate button', async () => {
        expect(wrapper.find('.sw-extension-store-landing-page__activate_button').isVisible()).toBe(true);
    });

    it('should go through a successful activation', async () => {
        expect(window.location.reload).not.toHaveBeenCalled();

        // trigger activation
        const activationButton = wrapper.find('.sw-extension-store-landing-page__activate_button');
        await activationButton.trigger('click');

        // check for loading wrapper
        const loadingWrapper = wrapper.find('.sw-extension-store-landing-page__wrapper-loading');
        expect(loadingWrapper.isVisible()).toBe(true);

        // expect reload on success
        expect(window.location.reload).toHaveBeenCalled();

        // wait for rerender
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // check if success message is shown
        const activationHeading = wrapper.find('.sw-extension-store-landing-page__wrapper-activated h2');
        expect(activationHeading.text()).toBe('sw-extension-store.landing-page.activationSuccessTitle');
    });

    it('should go through a unsuccessful activation with an error', async () => {
        successfulActivation = false;

        expect(window.location.reload).not.toHaveBeenCalled();

        // trigger activation
        const activationButton = wrapper.find('.sw-extension-store-landing-page__activate_button');
        await activationButton.trigger('click');

        // check for loading wrapper
        const loadingWrapper = wrapper.find('.sw-extension-store-landing-page__wrapper-loading');
        expect(loadingWrapper.isVisible()).toBe(true);

        // expect no reload on failure
        expect(window.location.reload).not.toHaveBeenCalled();

        // wait for rerender
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // check if error message is shown
        const activationHeading = wrapper.find('.sw-extension-store-landing-page__wrapper-activated h2');
        expect(activationHeading.text()).toBe('sw-extension-store.landing-page.activationErrorTitle');
    });
});
