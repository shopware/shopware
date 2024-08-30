import { mount } from '@vue/test-utils';

let successfulActivation = true;

async function createWrapper() {
    return mount(await wrapTestComponent('sw-extension-store-landing-page', { sync: true }), {
        global: {
            provide: {
                extensionHelperService: {
                    downloadAndActivateExtension: () => {
                        if (successfulActivation) {
                            return Promise.resolve();
                        }

                        return Promise.reject();
                    },
                },
            },
            stubs: {
                'sw-loader': true,
                'sw-icon': true,
                'sw-label': true,
                'sw-button': {
                    template: '<button @click="$emit(\'click\')"><slot></slot></button>',
                },
            },
        },
    });
}

/**
 * @package checkout
 */
describe('src/module/sw-extension/page/sw-extension-store-landing-page', () => {
    beforeAll(() => {
        delete window.location;
        window.location = { reload: jest.fn() };
        Shopware.Utils.debug.error = jest.fn();
    });

    beforeEach(async () => {
        successfulActivation = true;
        window.location.reload.mockClear();
        Shopware.Utils.debug.error.mockClear();
    });

    it('should show the activate button', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-extension-store-landing-page__activate_button').isVisible()).toBe(true);
    });

    it('should go through a successful activation', async () => {
        const wrapper = await createWrapper();

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
        const wrapper = await createWrapper();

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
