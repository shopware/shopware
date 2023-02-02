import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-bulk-edit/component/product/sw-bulk-edit-product-media';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-bulk-edit-product-media'), {
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
                            return Promise.resolve();
                        },
                    };
                }
            }
        },
        propsData: {
            disabled: false,
        },
    });
}

describe('sw-bulk-edit-product-media', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be disabled correctly', async () => {
        await wrapper.setProps({ disabled: true });
        expect(wrapper.find('sw-bulk-edit-product-media-form-stub').attributes().disabled).toBeTruthy();

        await wrapper.setProps({ disabled: false });
        expect(wrapper.find('sw-bulk-edit-product-media-form-stub').attributes().disabled).toBe(undefined);
    });
});
