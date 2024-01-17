/*
 * @package inventory
 */

import { shallowMount } from '@vue/test-utils';
import swProductCloneModal from 'src/module/sw-product/component/sw-product-clone-modal';

Shopware.Component.register('sw-product-clone-modal', swProductCloneModal);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-product-clone-modal'), {
        propsData: {
            product: {},
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    clone: jest.fn(() => Promise.resolve({
                        id: '1a2b3c',
                    })),
                    save: () => Promise.resolve(),
                    searchIds: () => Promise.resolve({ data: { length: 0 } }),
                }),
            },
            numberRangeService: {
                reserve: () => Promise.resolve({ number: 1337 }),
            },
        },
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
                name: 'shirt',
                variantListingConfig: {
                    mainVariantId: '1a2b3c',
                },
            },
        });

        await wrapper.vm.cloneParent({
            number: 250,
        });

        expect(wrapper.vm.repository.clone).toHaveBeenCalledWith(undefined, expect.anything(), {
            cloneChildren: false,
            overwrites: {
                active: false,
                mainVariantId: null,
                name: 'shirt global.default.copy',
                productNumber: 250,
                variantListingConfig: {
                    mainVariantId: null,
                },
            },
        });
    });
});
