/**
 * @package inventory
 */

import { mount } from '@vue/test-utils';

const { State } = Shopware;

const productMock = {
    id: 'productId',
    properties: [],
};

const assignedProductsMock = [
    {
        product: {
            id: 'productId',
            translated: {
                name: 'Product Name',
            },
        },
    },
    {
        product: {
            id: 'productVariantId',
            parentId: 'parentId',
            translated: {
                name: null,
            },
            variation: [
                {
                    group: 'Color',
                    option: 'Blue',
                },
            ],
        },
    },
];

const variantProductsMock = [
    {
        id: 'productVariantId',
        parentId: 'parentId',
        translated: {
            name: 'Variant Name',
        },
    },
];

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-product-cross-selling-assignment', {
            sync: true,
        }),
        {
            props: {
                assignedProducts: assignedProductsMock,
                crossSellingId: 'crossSellingId',
            },
            global: {
                stubs: {
                    'sw-entity-single-select': true,
                    'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                    'sw-product-variant-info': await wrapTestComponent('sw-product-variant-info'),
                    'sw-context-button': true,
                    'sw-data-grid-settings': true,
                    'sw-context-menu-item': true,
                    'sw-data-grid-column-position': true,
                    'sw-empty-state': true,
                    'sw-select-result': true,
                    'sw-checkbox-field': true,
                    'sw-icon': true,
                    'sw-data-grid-column-boolean': true,
                    'sw-data-grid-inline-edit': true,
                    'router-link': true,
                    'sw-button': true,
                    'sw-data-grid-skeleton': true,
                    'sw-highlight-text': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => {
                                return Promise.resolve(variantProductsMock);
                            },
                        }),
                    },
                },
            },
        },
    );
}

describe('module/sw-product/component/sw-product-cross-selling-assignment', () => {
    let wrapper;

    beforeAll(() => {
        State.registerModule('swProductDetail', {
            namespaced: true,
            state: {
                product: productMock,
            },
            getters: {
                isLoading: () => false,
            },
        });
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should fetch variants with inherited names if assignedProducts includes variants without name', async () => {
        const variantNames = wrapper.vm.variantNames;
        const variantProductIds = wrapper.vm.variantProductIds;

        expect(variantProductIds).toContain('productVariantId');
        expect(variantNames.productVariantId).toBe('Variant Name');

        const row1 = wrapper.find('.sw-data-grid__row.sw-data-grid__row--0');
        expect(row1.text()).toContain('Product Name');

        const row2 = wrapper.find('.sw-data-grid__row.sw-data-grid__row--1');
        expect(row2.text()).toContain('Variant Name');
        expect(row2.text()).toContain('Color');
        expect(row2.text()).toContain('Blue');
    });
});
