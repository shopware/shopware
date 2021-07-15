import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/component/sw-sales-channel-products-assignment-single-products';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-simple-search-field';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/entity/sw-entity-listing';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/context-menu/sw-context-button';

let productData = [];

function setProductData(products) {
    productData = [...products];
    productData.total = 3;
    productData.criteria = {
        page: 1,
        limit: 25
    };
}

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-sales-channel-products-assignment-single-products'), {
        stubs: {
            'sw-container': true,
            'sw-card': {
                template: '<div><slot></slot><slot name="grid"></slot></div>'
            },
            'sw-card-section': {
                template: '<div><slot></slot></div>'
            },
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-field-error': true,
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-entity-listing': Shopware.Component.build('sw-entity-listing'),
            'sw-data-grid': Shopware.Component.build('sw-data-grid'),
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-field': Shopware.Component.build('sw-field'),
            'sw-simple-search-field': Shopware.Component.build('sw-simple-search-field'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
            'sw-context-button': Shopware.Component.build('sw-context-button'),
            'sw-context-menu-item': true,
            'sw-empty-state': true,
            'sw-modal': true,
            'sw-tabs': true,
            'sw-tab-items': true,
            'sw-icon': true,
            'sw-pagination': true,
            'sw-data-grid-skeleton': true,
            'sw-data-grid-settings': true
        },
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        search: () => Promise.resolve(productData)
                    };
                }
            },
            validationService: {}
        },
        propsData: {
            salesChannel: {
                id: 1,
                name: 'Headless'
            }
        }
    });
}

describe('src/module/sw-sales-channel/component/sw-sales-channel-products-assignment-single-products', () => {
    it('should display empty state when product data is empty', async () => {
        setProductData([]);
        const wrapper = createWrapper();

        expect(wrapper.find('sw-empty-state-stub').exists()).toBeTruthy();
    });

    it('should display data grid when there is product data', async () => {
        setProductData([
            {
                name: 'Test product 1',
                productNumber: '1'
            }
        ]);

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-data-grid').exists()).toBeTruthy();
    });

    it('should emit selected products', async () => {
        setProductData([
            {
                id: 1,
                name: 'Test product 1',
                productNumber: '1'
            },
            {
                id: 2,
                name: 'Test product 2',
                productNumber: '2'
            },
            {
                id: 3,
                name: 'Test product 3',
                productNumber: '3'
            }
        ]);

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.find('.sw-data-grid__select-all .sw-field__checkbox input').trigger('click');
        expect(wrapper.emitted()['selection-change'][1]).toEqual([
            [
                {
                    id: 1,
                    name: 'Test product 1',
                    productNumber: '1'
                },
                {
                    id: 2,
                    name: 'Test product 2',
                    productNumber: '2'
                },
                {
                    id: 3,
                    name: 'Test product 3',
                    productNumber: '3'
                }
            ],
            'singleProducts'
        ]);
    });

    it('should get products when searching', async () => {
        const wrapper = createWrapper();
        wrapper.vm.getProducts = jest.fn(() => {
            return Promise.resolve();
        });

        await wrapper.setData({
            page: 2
        });

        expect(wrapper.vm.page).toEqual(2);

        await wrapper.vm.onChangeSearchTerm('Standard prices');

        expect(wrapper.vm.searchTerm).toBe('Standard prices');
        expect(wrapper.vm.page).toEqual(1);
        expect(wrapper.vm.getProducts).toHaveBeenCalledTimes(1);

        wrapper.vm.getProducts.mockRestore();
    });
});
