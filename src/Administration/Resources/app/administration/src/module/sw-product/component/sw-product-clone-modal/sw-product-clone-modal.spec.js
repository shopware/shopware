/*
 * @package inventory
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-product-clone-modal', { sync: true }), {
        props: {
            product: {},
        },
        global: {
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
        },
    });
}


describe('src/module/sw-product/component/sw-product-clone-modal', () => {
    /** @type Wrapper */
    let wrapper;

    it('should be a Vue.JS component', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should clone parent without mainVariantId', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
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

        expect(wrapper.vm.repository.clone).toHaveBeenCalledWith(undefined, {
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
        }, expect.anything());
    });
});
