import { shallowMount } from '@vue/test-utils';
import swBulkEditProductMedia from 'src/module/sw-bulk-edit/component/product/sw-bulk-edit-product-media';

Shopware.Component.register('sw-bulk-edit-product-media', swBulkEditProductMedia);

/**
 * @package system-settings
 * @returns {Promise<Wrapper<Vue>>}
 */
async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-bulk-edit-product-media'), {
        stubs: {
            'sw-bulk-edit-product-media-form': true,
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
        propsData: {
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

    afterEach(() => {
        wrapper.destroy();
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
