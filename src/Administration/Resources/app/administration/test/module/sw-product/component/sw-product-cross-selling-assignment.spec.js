import { shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-product-variant-info';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/module/sw-product/component/sw-product-cross-selling-assignment';

const { State } = Shopware;

const productMock = {
    id: 'productId',
    properties: []
};

const assignedProductsMock = [
    {
        product: {
            id: 'productId',
            translated: {
                name: 'Product Name'
            }
        }
    },
    {
        product: {
            id: 'productVariantId',
            parentId: 'parentId',
            translated: {
                name: null
            },
            variation: [
                {
                    group: 'Color',
                    option: 'Blue'
                }
            ]
        }
    }
];

const variantProductsMock = [
    {
        id: 'productVariantId',
        parentId: 'parentId',
        translated: {
            name: 'Variant Name'
        }
    }
];

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-product-cross-selling-assignment'), {
        stubs: {
            'sw-entity-single-select': true,
            'sw-data-grid': Shopware.Component.build('sw-data-grid'),
            'sw-product-variant-info': Shopware.Component.build('sw-product-variant-info'),
            'sw-context-button': true,
            'sw-data-grid-settings': true,
            'sw-context-menu-item': true,
            'sw-data-grid-column-position': true,
            'sw-empty-state': true
        },
        propsData: {
            assignedProducts: assignedProductsMock,
            crossSellingId: 'crossSellingId'
        },
        state: {
            product: productMock
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve(variantProductsMock);
                    }
                })
            }
        },
        computed: {
            searchCriteria() {
                return {};
            }
        }
    });
}

describe('module/sw-product/component/sw-product-cross-selling-assignment', () => {
    let wrapper;

    beforeAll(() => {
        State.registerModule('swProductDetail', {
            namespaced: true,
            state: {
                product: productMock
            },
            getters: {
                isLoading: () => false
            }
        });
    });

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should fetch variants with inherited names if assignedProducts includes variants without name', () => {
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
