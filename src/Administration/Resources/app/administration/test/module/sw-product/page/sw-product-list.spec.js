import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-product/page/sw-product-list';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/data-grid/sw-data-grid-settings';
import 'src/app/component/entity/sw-entity-listing';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/context-menu/sw-context-menu-item';
import 'src/app/component/base/sw-button';
import 'src/app/component/grid/sw-pagination';
import 'src/app/component/base/sw-empty-state';
import 'src/app/component/structure/sw-page';

function mockContext() {
    return {
        apiPath: 'http://shopware.local/api',
        apiResourcePath: 'http://shopware.local/api/v2',
        apiVersion: 2,
        assetsPath: 'http://shopware.local/bundles/',
        basePath: '',
        host: 'shopware.local',
        inheritance: false,
        installationPath: 'http://shopware.local',
        languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
        liveVersionId: '0fa91ce3e96a4bc2be4bd9ce752c3425',
        pathInfo: '/admin',
        port: 80,
        scheme: 'http',
        schemeAndHttpHost: 'http://shopware.local',
        uri: 'http://shopware.local/admin'
    };
}

function mockCriteria() {
    return {
        limit: 25,
        page: 1,
        resetSorting() {
            this.sortings = [];
        },
        addSorting(sorting) {
            this.sortings.push(sorting);
        }
    };
}

function getProductData(criteria) {
    const products = [
        {
            active: true,
            stock: 333,
            availableStock: 333,
            available: true,
            price: [
                {
                    currencyId: '3f9c8b1b2b1d4d43a89cf267c3d43377',
                    net: 373.83,
                    gross: 400,
                    linked: true
                },
                {
                    currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                    net: 560.75,
                    gross: 600,
                    linked: true
                }
            ],
            productNumber: 'SW10001',
            name: 'Product 2',
            id: 'dcc37f845b664e24b5b2e6e77c078e6c'
        },
        {
            active: true,
            stock: 333,
            availableStock: 333,
            available: true,
            price: [
                {
                    currencyId: '3f9c8b1b2b1d4d43a89cf267c3d43377',
                    net: 20.56,
                    gross: 22,
                    linked: true
                },
                {
                    currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                    net: 186.89,
                    gross: 200,
                    linked: true
                }
            ],
            productNumber: 'SW10000',
            name: 'Product 1',
            id: 'bc5ff49955be4b919053add552c2815d'
        }
    ];

    // check if grid is sorting for currency
    const sortingForCurrency = criteria.sortings.some(sortAttr => sortAttr.field === 'price');

    if (sortingForCurrency) {
        products.reverse();
    }

    products.sortings = [];
    products.total = products.length;
    products.criteria = mockCriteria();
    products.context = mockContext();

    return products;
}

function getCurrencyData() {
    return [
        {
            factor: 1,
            symbol: '€',
            isoCode: 'EUR',
            shortName: 'EUR',
            name: 'Euro',
            decimalPrecision: 2,
            position: 1,
            isSystemDefault: true,
            id: 'b7d2554b0ce847cd82f3ac9bd1c0dfca'
        },
        {
            factor: 1.0457384950,
            symbol: '£',
            isoCode: 'GBP',
            shortName: 'GBP',
            name: 'Pound',
            decimalPrecision: 2,
            position: 1,
            isSystemDefault: true,
            id: 'fce3465831e8639bb2ea165d0fcf1e8b'
        }
    ];
}

function createWrapper() {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});
    localVue.filter('currency', (currency) => currency);

    return shallowMount(Shopware.Component.build('sw-product-list'), {
        localVue,
        mocks: {
            $route: {},
            $router: { replace: () => {} },
            $device: { onResize: () => {} },
            $tc: () => {},
            $te: () => {}
        },
        provide: {
            numberRangeService: {},
            repositoryFactory: {
                create: (name) => {
                    if (name === 'product') {
                        return { search: (criteria) => Promise.resolve(getProductData(criteria)) };
                    }

                    return { search: () => Promise.resolve(getCurrencyData()) };
                }
            }
        },
        stubs: {
            'sw-page': '<div><slot name="content"></slot></div>',
            'sw-entity-listing': Shopware.Component.build('sw-entity-listing'),
            'sw-context-button': '<div></div>',
            'sw-context-menu-item': '<div></div>',
            'sw-data-grid-settings': '<div></div>',
            'sw-empty-state': '<div></div>',
            'sw-pagination': '<div></div>',
            'sw-icon': '<div></div>',
            'sw-button': '<div></div>',
            'sw-sidebar': '<div></div>',
            'sw-sidebar-item': '<div></div>',
            'router-link': '<div></div>',
            'sw-language-switch': '<div></div>',
            'sw-notification-center': '<div></div>',
            'sw-search-bar': '<div></div>',
            'sw-loader': '<div></div>',
            'sw-data-grid-skeleton': '<div class="sw-data-grid-skeleton"></div>',
            'sw-checkbox-field': '<div></div>',
            'sw-media-preview-v2': '<div></div>',
            'sw-color-badge': '<div></div>'
        }
    });
}

describe('module/sw-product/page/sw-product-list', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.JS component', () => {
        expect(wrapper.isVueInstance()).toBe(true);
    });

    it('should sort grid when sorting for price', async () => {
        // load content of grid
        await wrapper.vm.getList();

        const priceCells = wrapper.findAll('.sw-data-grid__cell--price-EUR');
        const firstPriceCell = priceCells.at(0);
        const secondPriceCell = priceCells.at(1);

        // assert cells have correct values
        expect(firstPriceCell.text()).toBe('600');
        expect(secondPriceCell.text()).toBe('200');

        // get header which sorts grid when clicking on it
        const currencyColumnHeader = wrapper.find('.sw-data-grid__cell--header.sw-data-grid__cell--4');

        // sort grid after price
        await currencyColumnHeader.trigger('click');

        const sortedPriceCells = wrapper.findAll('.sw-data-grid__cell--price-EUR');
        const firstSortedPriceCell = sortedPriceCells.at(0);
        const secondSortedPriceCell = sortedPriceCells.at(1);

        // assert that order of values has changed
        expect(firstSortedPriceCell.text()).toBe('200');
        expect(secondSortedPriceCell.text()).toBe('600');

        // verify that grid did not crash when sorting for prices
        const skeletonElement = wrapper.find('.sw-data-grid-skeleton');
        expect(skeletonElement.exists()).toBe(false);
    });
});
