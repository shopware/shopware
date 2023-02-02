import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-product/component/sw-product-clone-modal';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-product-clone-modal'), {
        propsData: {
            product: {}
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    clone: jest.fn(() => Promise.resolve({
                        id: '1a2b3c'
                    })),
                    save: () => Promise.resolve()
                })
            },
            numberRangeService: {
                reserve: () => Promise.resolve()
            }
        }
    });
}


describe('src/module/sw-product/component/sw-product-clone-modal', () => {
    /** @type Wrapper */
    let wrapper;

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should clone parent without mainVariantId', async () => {
        wrapper = await createWrapper();
        await wrapper.setData({
            product: {
                name: 'shirt'
            }
        });

        await wrapper.vm.cloneParent({
            number: 250
        });

        expect(wrapper.vm.repository.clone).toHaveBeenCalledWith(undefined, expect.anything(), {
            cloneChildren: false,
            overwrites: {
                active: false,
                mainVariantId: null,
                name: 'shirt global.default.copy',
                productNumber: 250
            }
        });
    });
});
