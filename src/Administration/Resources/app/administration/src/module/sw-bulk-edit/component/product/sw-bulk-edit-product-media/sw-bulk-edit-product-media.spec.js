import { mount } from '@vue/test-utils';

/**
 * @package services-settings
 * @group disabledCompat
 * @returns {Promise<Wrapper<Vue>>}
 */
async function createWrapper() {
    return mount(await wrapTestComponent('sw-bulk-edit-product-media', { sync: true }), {
        global: {
            stubs: {
                'sw-bulk-edit-product-media-form': true,
                'sw-media-modal-v2': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            create: () => {
                                return Promise.resolve();
                            },
                            search: () => {
                                return Promise.resolve({
                                    first: () => null,
                                });
                            },
                        };
                    },
                },
            },
        },
        props: {
            disabled: false,
        },
    });
}

describe('sw-bulk-edit-product-media', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();

        await flushPromises();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be disabled correctly', async () => {
        await wrapper.setProps({ disabled: true });
        expect(wrapper.find('sw-bulk-edit-product-media-form-stub').attributes().disabled).toBeTruthy();

        await wrapper.setProps({ disabled: false });
        expect(wrapper.find('sw-bulk-edit-product-media-form-stub').attributes().disabled).toBeUndefined();
    });
});
