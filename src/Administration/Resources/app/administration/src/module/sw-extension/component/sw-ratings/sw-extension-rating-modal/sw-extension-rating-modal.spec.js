import { mount } from '@vue/test-utils';

/**
 * @package checkout
 */
describe('src/module/sw-extension/component/sw-ratings/sw-extension-rating-modal', () => {
    async function createWrapper() {
        return mount(
            await wrapTestComponent('sw-extension-rating-modal', {
                sync: true,
            }),
            {
                global: {
                    provide: {
                        extensionStoreActionService: {},
                    },
                    stubs: {
                        'sw-extension-review-creation-inputs': true,
                        'sw-gtc-checkbox': true,
                        'sw-button': true,
                        'sw-button-process': true,
                    },
                },
                props: {
                    extension: {},
                },
            },
        );
    }

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });
});
