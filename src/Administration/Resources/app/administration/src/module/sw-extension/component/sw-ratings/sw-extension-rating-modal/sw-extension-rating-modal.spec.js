import { mount } from '@vue/test-utils';

/**
 * @package services-settings
 */
describe('src/module/sw-extension/component/sw-ratings/sw-extension-rating-modal', () => {
    async function createWrapper() {
        return mount(await wrapTestComponent('sw-extension-rating-modal', { sync: true }), {
            global: {
                provide: {
                    extensionStoreActionService: {},
                },
            },
            props: {
                extension: {},
            },
        });
    }

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });
});
