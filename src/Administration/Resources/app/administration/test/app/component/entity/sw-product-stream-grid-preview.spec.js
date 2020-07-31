import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/entity/sw-product-stream-grid-preview';
import 'src/app/component/base/sw-empty-state';
import 'src/app/component/data-grid/sw-data-grid';

describe('components/entity/sw-product-stream-grid-preview.spec', () => {
    let wrapper;
    const localVue = createLocalVue();

    const mockFilter = [{ type: 'equals', field: 'parentId', value: null }];

    const mockProducts = [
        { name: 'Product 1', price: [{ currencyId: 'uuid1337', gross: 444 }] },
        { name: 'Product 2', price: [{ currencyId: 'uuid1337', gross: 25 }] },
        { name: 'Product 3', price: [{ currencyId: 'uuid1337', gross: 36 }] },
        { name: 'Product 4', price: [{ currencyId: 'uuid1337', gross: 1258 }] }
    ];
    mockProducts.total = 4;
    mockProducts.criteria = {
        page: 1,
        limit: 25
    };

    const mockCurrency = {
        id: 'uuid1337',
        name: 'Euro',
        isoCode: 'EUR',
        isSystemCurrency: true,
        symbol: '€'
    };

    localVue.filter('asset', key => key);
    localVue.filter('currency', key => key);

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-product-stream-grid-preview'), {
            localVue,
            stubs: {
                'sw-empty-state': Shopware.Component.build('sw-empty-state'),
                'sw-simple-search-field': true,
                'sw-data-grid': Shopware.Component.build('sw-data-grid'),
                'sw-data-grid-skeleton': true,
                'sw-pagination': true,
                'sw-data-grid-column-boolean': true,
                'router-link': true,
                'sw-product-variant-info': true
            },
            mocks: {
                $tc: key => key,
                $te: key => key,
                $route: { meta: { $module: { icon: 'default' } } },
                $device: { onResize: key => key }
            },
            propsData: {
                filters: null
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        get: () => Promise.resolve(mockCurrency),
                        search: () => Promise.resolve(mockProducts)
                    })
                }
            }
        });
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should render empty state when no filter is set', () => {
        expect(wrapper.find('.sw-empty-state').exists()).toBeTruthy();
    });

    it('should load products with correct criteria when filters are being set', async () => {
        wrapper.setProps({
            filters: mockFilter
        });

        const spyLoadProducts = jest.spyOn(wrapper.vm, 'loadProducts');
        await wrapper.vm.$nextTick();

        expect(spyLoadProducts).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.products).toBe(mockProducts);
        expect(wrapper.vm.total).toBe(mockProducts.total);
        expect(wrapper.vm.systemCurrency).toBe(mockCurrency);
        expect(wrapper.vm.filters).toBe(mockFilter);
        expect(wrapper.vm.criteria.filters).toBe(wrapper.vm.filters);
        expect(wrapper.vm.criteria.associations[0].association).toBe('manufacturer');
    });

    it('should render data grid when products were loaded', async () => {
        wrapper.setProps({
            filters: mockFilter
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-empty-state').exists()).toBeFalsy();
        expect(wrapper.find('.sw-data-grid').exists()).toBeTruthy();
    });

    it('should render correct default columns for data grid', async () => {
        wrapper.setProps({
            filters: mockFilter
        });

        await wrapper.vm.$nextTick();

        const columns = wrapper.findAll('.sw-data-grid__cell--header');
        const colName = columns.at(0);
        const colManufacturer = columns.at(1);
        const colActive = columns.at(2);
        const colPrice = columns.at(3);
        const colStock = columns.at(4);

        // Ensure overall column amount
        expect(columns.length).toBe(5);

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
        wrapper.setProps({
            filters: mockFilter,
            columns: [
                {
                    property: 'name',
                    label: 'Name'
                }, {
                    property: 'manufacturer',
                    label: 'Manufacturer'
                }
            ]
        });

        await wrapper.vm.$nextTick();

        const columns = wrapper.findAll('.sw-data-grid__cell--header');
        const colName = columns.at(0);
        const colManufacturer = columns.at(1);

        // Ensure overall column amount
        expect(columns.length).toBe(2);

        // Verify each column has correct label
        expect(colName.find('.sw-data-grid__cell-content').text()).toBe('Name');
        expect(colManufacturer.find('.sw-data-grid__cell-content').text()).toBe('Manufacturer');
    });

    it('should render a data grid row for each product', async () => {
        wrapper.setProps({
            filters: mockFilter
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('.sw-data-grid__body .sw-data-grid__row').length).toBe(4);
    });

    it('should send request with term when updating searchTern', async () => {
        wrapper.setProps({
            filters: mockFilter
        });

        wrapper.setData({
            searchTerm: 'Desired product'
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.criteria.term).toBe('Desired product');
    });
});
