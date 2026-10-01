/**
 * @sw-package inventory
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
                        clone: jest.fn(() =>
                            Promise.resolve({
                                id: '1a2b3c',
                            }),
                        ),
                        save: () => Promise.resolve(),
                        searchIds: () => Promise.resolve({ data: { length: 0 } }),
                    }),
                },
                numberRangeService: {
                    reserve: () => Promise.resolve({ number: 1337 }),
                },
            },
            stubs: {
                'mt-progress-bar': true,
            },
        },
    });
}

describe('src/module/sw-product/component/sw-product-clone-modal', () => {
    /** @type Wrapper */
    let wrapper;

    it('should clone parent without mainVariantId', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            product: {
                name: 'shirt',
                variantListingConfig: {
                    mainVariantId: '1a2b3c',
                },
                childCount: 1,
            },
        });

        await wrapper.vm.cloneParent({
            number: 250,
        });

        expect(wrapper.vm.repository.clone).toHaveBeenCalledWith(
            undefined,
            {
                cloneChildren: false,
                overwrites: {
                    active: false,
                    mainVariantId: null,
                    canonicalProductId: null,
                    name: 'shirt global.default.copy',
                    productNumber: 250,
                    variantListingConfig: {
                        mainVariantId: null,
                    },
                },
            },
            expect.anything(),
        );
    });

    it('should not change the original product', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const product = {
            name: 'shirt',
            variantListingConfig: {
                mainVariantId: '1a2b3c',
            },
            childCount: 1,
        };

        await wrapper.setProps({
            product: product,
        });

        expect(product.variantListingConfig.mainVariantId).toBe('1a2b3c');
    });

    it('should save a new product before reserving the duplicate product number', async () => {
        const product = {
            id: 'product-id',
            name: 'shirt',
            productNumber: 'SW10011',
            childCount: 0,
        };
        const save = jest.fn(() => Promise.resolve());
        const clone = jest.fn(() => Promise.resolve({ id: 'duplicate-id' }));
        const reserve = jest.fn().mockResolvedValueOnce({ number: 'SW10012' }).mockResolvedValueOnce({ number: 'SW10013' });

        wrapper = await mount(await wrapTestComponent('sw-product-clone-modal', { sync: true }), {
            props: {
                product,
                productNumberPreview: 'SW10011',
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            clone,
                            save,
                            searchIds: () => Promise.resolve({ data: { length: 0 } }),
                        }),
                    },
                    numberRangeService: {
                        reserve,
                    },
                },
                stubs: {
                    'mt-progress-bar': true,
                },
            },
        });

        await flushPromises();

        expect(save).toHaveBeenCalledWith(product);
        expect(reserve).toHaveBeenNthCalledWith(1, 'product');
        expect(reserve).toHaveBeenNthCalledWith(2, 'product');
        expect(save.mock.invocationCallOrder[0]).toBeLessThan(reserve.mock.invocationCallOrder[1]);
        expect(clone).toHaveBeenCalledWith(
            'product-id',
            expect.objectContaining({
                overwrites: expect.objectContaining({
                    productNumber: 'SW10013',
                }),
            }),
            expect.anything(),
        );
    });
});
