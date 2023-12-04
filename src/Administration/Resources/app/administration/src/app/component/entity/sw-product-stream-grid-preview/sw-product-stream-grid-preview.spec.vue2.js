import { createLocalVue, shallowMount } from '@vue/test-utils_v2';
import 'src/app/component/entity/sw-product-stream-grid-preview';
import 'src/app/component/base/sw-empty-state';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/base/sw-simple-search-field';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/field-base/sw-base-field';

const mockProducts = [{
    id: 1,
    name: 'Product 1',
    price: [{ currencyId: 'uuid1337', gross: 444 }],
    manufacturer: { name: 'Test' },
}, {
    id: 2,
    name: 'Product 2',
    price: [{ currencyId: 'uuid1337', gross: 25 }],
    manufacturer: { name: 'Test' },
}, {
    id: 3,
    name: 'Product 3',
    price: [{ currencyId: 'uuid1337', gross: 36 }],
    manufacturer: { name: 'Test' },
}, {
    id: 4,
    name: 'Product 4',
    price: [{ currencyId: 'uuid1337', gross: 1258 }],
    manufacturer: { name: 'Test' },
}];

mockProducts.total = 4;
mockProducts.criteria = {
    page: 1,
    limit: 25,
};

const mockCurrency = {
    id: 'uuid1337',
    name: 'Euro',
    isoCode: 'EUR',
    isSystemCurrency: true,
    symbol: '€',
};

const createWrapper = async () => {
    const localVue = createLocalVue();
    localVue.filter('asset', key => key);
    localVue.filter('currency', key => key);

    return shallowMount(await Shopware.Component.build('sw-product-stream-grid-preview'), {
        localVue,
        stubs: {
            'sw-empty-state': await Shopware.Component.build('sw-empty-state'),
            'sw-simple-search-field': await Shopware.Component.build('sw-simple-search-field'),
            'sw-field': await Shopware.Component.build('sw-field'),
            'sw-text-field': await Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-data-grid': await Shopware.Component.build('sw-data-grid'),
            'sw-data-grid-skeleton': true,
            'sw-pagination': true,
            'sw-data-grid-column-boolean': true,
            'router-link': true,
            'sw-product-variant-info': true,
            'sw-checkbox-field': await Shopware.Component.build('sw-checkbox-field'),
            'sw-icon': true,
            'sw-field-error': true,
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
        },
        mocks: {
            $route: { meta: { $module: { icon: 'default' } } },
        },
        propsData: {
            filters: null,
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    get: () => Promise.resolve(mockCurrency),
                    search: () => Promise.resolve(mockProducts),
                }),
            },
            validationService: {},
        },
        attachTo: document.body,
    });
};

describe('components/entity/sw-product-stream-grid-preview.spec', () => {
    let wrapper;
    const mockFilter = [{ type: 'equals', field: 'parentId', value: null }];

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render empty state when no filter is set', async () => {
        expect(wrapper.find('.sw-empty-state').exists()).toBeTruthy();
    });

    it('should load products with correct criteria when filters are being set', async () => {
        const spyLoadProducts = jest.spyOn(wrapper.vm, 'loadProducts');

        await wrapper.setProps({
            filters: mockFilter,
        });

        const displayGroupFilter = {
            operator: 'AND',
            queries: [{
                field: 'displayGroup',
                type: 'equals',
                value: null,
            }],
            type: 'not',
        };

        await wrapper.vm.$nextTick();

        expect(spyLoadProducts).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.products).toBe(mockProducts);
        expect(wrapper.vm.total).toBe(mockProducts.total);
        expect(wrapper.vm.systemCurrency).toBe(mockCurrency);
        expect(wrapper.vm.filters).toBe(mockFilter);
        expect(wrapper.vm.criteria.filters).toEqual([...wrapper.vm.filters, displayGroupFilter]);
        expect(wrapper.vm.criteria.associations[0].association).toBe('manufacturer');
    });

    it('should render data grid when products were loaded', async () => {
        await wrapper.setProps({
            filters: mockFilter,
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-empty-state').exists()).toBeFalsy();
        expect(wrapper.find('.sw-data-grid').exists()).toBeTruthy();
    });

    it('should render correct default columns for data grid', async () => {
        await wrapper.setProps({
            filters: mockFilter,
        });

        await wrapper.vm.$nextTick();

        const columns = wrapper.findAll('.sw-data-grid__cell--header');
        const colName = columns.at(0);
        const colManufacturer = columns.at(1);
        const colActive = columns.at(2);
        const colPrice = columns.at(3);
        const colStock = columns.at(4);

        // Ensure overall column amount
        expect(columns).toHaveLength(5);

        // Verify each column has correct label
        expect(colName.find('.sw-data-grid__cell-content').text())
            .toBe('sw-product-stream.filter.values.product');
        expect(colManufacturer.find('.sw-data-grid__cell-content').text())
            .toBe('sw-product-stream.filter.values.manufacturer');
        expect(colActive.find('.sw-data-grid__cell-content').text())
            .toBe('sw-product-stream.filter.values.active');
        expect(colPrice.find('.sw-data-grid__cell-content').text())
            .toBe('sw-product-stream.filter.values.price');
        expect(colStock.find('.sw-data-grid__cell-content').text())
            .toBe('sw-product-stream.filter.values.stock');
    });

    it('should render correct columns when using columns prop', async () => {
        await wrapper.setProps({
            filters: mockFilter,
            columns: [
                {
                    property: 'name',
                    label: 'Name',
                }, {
                    property: 'manufacturer',
                    label: 'Manufacturer',
                },
            ],
        });

        await wrapper.vm.$nextTick();

        const columns = wrapper.findAll('.sw-data-grid__cell--header');
        const colName = columns.at(0);
        const colManufacturer = columns.at(1);

        // Ensure overall column amount
        expect(columns).toHaveLength(2);

        // Verify each column has correct label
        expect(colName.find('.sw-data-grid__cell-content').text()).toBe('Name');
        expect(colManufacturer.find('.sw-data-grid__cell-content').text()).toBe('Manufacturer');
    });

    it('should render a data grid row for each product', async () => {
        await wrapper.setProps({
            filters: mockFilter,
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('.sw-data-grid__body .sw-data-grid__row')).toHaveLength(4);
    });

    it('should send request with term when updating searchTerm', async () => {
        // Recreate the wrapper to exchange debounce implementation
        wrapper.destroy();
        Shopware.Utils.debounce = jest.fn((fn) => fn);
        wrapper = await createWrapper();

        await wrapper.setProps({
            filters: mockFilter,
        });

        const searchField = wrapper.find('.sw-product-stream-grid-preview__search-field input');
        await searchField.setValue('Desired product');

        expect(wrapper.vm.criteria.term).toBe('Desired product');

        Shopware.Utils.debounce.mockReset();
    });

    it('should emit event when selection change with correct data', async () => {
        await wrapper.setProps({
            filters: mockFilter,
            showSelection: true,
        });

        await wrapper.vm.$nextTick();

        await wrapper.find('.sw-data-grid__row--0 .sw-field--checkbox input').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.emitted()['selection-change'].length).toBeTruthy();
        expect(wrapper.emitted()['selection-change'][0]).toEqual([
            {
                1: {
                    id: 1,
                    name: 'Product 1',
                    price: [{ currencyId: 'uuid1337', gross: 444 }],
                    manufacturer: { name: 'Test' },
                },
            },
        ]);
    });
});
