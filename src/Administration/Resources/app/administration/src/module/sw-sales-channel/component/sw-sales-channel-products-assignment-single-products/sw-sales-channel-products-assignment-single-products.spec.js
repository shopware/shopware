/**
 * @package buyers-experience
 */

import { mount } from '@vue/test-utils';

let productData = [];

function mockCriteria() {
    return {
        limit: 25,
        page: 1,
        sortings: [{ field: 'name', naturalSorting: false, order: 'ASC' }],
        resetSorting() {
            this.sortings = [];
        },
        addSorting(sorting) {
            this.sortings.push(sorting);
        },
    };
}

function setProductData(products) {
    productData = [...products];
    productData.total = 3;
    productData.criteria = {
        page: 1,
        limit: 25,
    };
}

async function createWrapper() {
    return mount(await wrapTestComponent('sw-sales-channel-products-assignment-single-products', { sync: true }), {
        global: {
            stubs: {
                'sw-container': true,
                'sw-card': {
                    template: '<div><slot></slot><slot name="grid"></slot></div>',
                },
                'sw-card-section': {
                    template: '<div><slot></slot></div>',
                },
                'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                'sw-base-field': await wrapTestComponent('sw-base-field', {
                    sync: true,
                }),
                'sw-field-error': true,
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field', { sync: true }),
                'sw-entity-listing': await wrapTestComponent('sw-entity-listing', { sync: true }),
                'sw-data-grid': await wrapTestComponent('sw-data-grid', {
                    sync: true,
                }),
                'sw-button': await wrapTestComponent('sw-button', {
                    sync: true,
                }),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                'sw-simple-search-field': await wrapTestComponent('sw-simple-search-field', { sync: true }),
                'sw-text-field': await wrapTestComponent('sw-text-field', {
                    sync: true,
                }),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field', { sync: true }),
                'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                'sw-context-button': await wrapTestComponent('sw-context-button', { sync: true }),
                'sw-context-menu-item': true,
                'sw-empty-state': true,
                'sw-modal': true,
                'sw-tabs': true,
                'sw-tab-items': true,
                'sw-icon': true,
                'sw-pagination': true,
                'sw-data-grid-skeleton': true,
                'sw-data-grid-settings': true,
                'sw-text-field-deprecated': true,
                'sw-bulk-edit-modal': true,
                'sw-data-grid-column-boolean': true,
                'sw-data-grid-inline-edit': true,
                'router-link': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(productData),
                        };
                    },
                },
                validationService: {},
            },
        },
        props: {
            salesChannel: {
                id: 1,
                name: 'Headless',
            },
            containerStyle: {},
        },
        attachTo: document.body,
    });
}

describe('src/module/sw-sales-channel/component/sw-sales-channel-products-assignment-single-products', () => {
    it('should display empty state when product data is empty', async () => {
        setProductData([]);
        const wrapper = await createWrapper();

        expect(wrapper.find('sw-empty-state-stub').exists()).toBeTruthy();
    });

    it('should display data grid when there is product data', async () => {
        setProductData([
            {
                name: 'Test product 1',
                productNumber: '1',
            },
        ]);

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-data-grid').exists()).toBeTruthy();
    });

    it('should emit selected products', async () => {
        setProductData([
            {
                id: 1,
                name: 'Test product 1',
                productNumber: '1',
            },
            {
                id: 2,
                name: 'Test product 2',
                productNumber: '2',
            },
            {
                id: 3,
                name: 'Test product 3',
                productNumber: '3',
            },
        ]);

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.find('.sw-data-grid__select-all .sw-field__checkbox input').trigger('click');
        expect(wrapper.emitted('selection-change').at(-1)).toEqual([
            [
                {
                    id: 1,
                    name: 'Test product 1',
                    productNumber: '1',
                },
                {
                    id: 2,
                    name: 'Test product 2',
                    productNumber: '2',
                },
                {
                    id: 3,
                    name: 'Test product 3',
                    productNumber: '3',
                },
            ],
            'singleProducts',
        ]);
    });

    it('should get products when searching', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.getProducts = jest.fn(() => {
            return Promise.resolve();
        });

        await wrapper.setData({
            page: 2,
        });

        expect(wrapper.vm.page).toBe(2);

        await wrapper.vm.onChangeSearchTerm('Standard prices');

        expect(wrapper.vm.searchTerm).toBe('Standard prices');
        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.getProducts).toHaveBeenCalledTimes(1);

        wrapper.vm.getProducts.mockRestore();
    });

    it('should get products when changing page', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.getProducts = jest.fn();
        expect(wrapper.vm.productCriteria.sortings).toEqual([]);
        wrapper.vm.products.criteria = mockCriteria();

        await wrapper.vm.onChangePage({ page: 2, limit: 25 });

        expect(wrapper.vm.page).toBe(2);
        expect(wrapper.vm.limit).toBe(25);
        expect(wrapper.vm.productCriteria.sortings).toEqual([
            { field: 'name', naturalSorting: false, order: 'ASC' },
        ]);
        expect(wrapper.vm.getProducts).toHaveBeenCalledTimes(1);
        wrapper.vm.getProducts.mockRestore();
    });
});
