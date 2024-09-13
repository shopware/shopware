/**
 * @package admin
 */
import { mount } from '@vue/test-utils';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';

const CURRENCY_ID = {
    EURO: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
    POUND: 'fce3465831e8639bb2ea165d0fcf1e8b',
};

function mockContext() {
    return {
        apiPath: 'http://shopware.local/api',
        apiResourcePath: 'http://shopware.local/api/v2',
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
        uri: 'http://shopware.local/admin',
    };
}

function mockPrices() {
    return [
        {
            currencyId: CURRENCY_ID.POUND,
            net: 373.83,
            gross: 400,
            linked: true,
        },
        {
            currencyId: CURRENCY_ID.EURO,
            net: 560.75,
            gross: 600,
            linked: true,
        },
    ];
}

function mockCriteria() {
    return {
        limit: 25,
        page: 1,
        sortings: [],
        resetSorting() {
            this.sortings = [];
        },
        addSorting(sorting) {
            this.sortings.push(sorting);
        },
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
                    currencyId: CURRENCY_ID.POUND,
                    net: 373.83,
                    gross: 400,
                    linked: true,
                },
                {
                    currencyId: CURRENCY_ID.EURO,
                    net: 560.75,
                    gross: 600,
                    linked: true,
                },
            ],
            productNumber: 'SW10001',
            name: 'Product 2',
            id: 'dcc37f845b664e24b5b2e6e77c078e6c',
            manufacturer: {
                name: 'Manufacturer B',
            },
        },
        {
            active: true,
            stock: 333,
            availableStock: 333,
            available: true,
            price: [
                {
                    currencyId: CURRENCY_ID.POUND,
                    net: 20.56,
                    gross: 22,
                    linked: true,
                },
                {
                    currencyId: CURRENCY_ID.EURO,
                    net: 186.89,
                    gross: 200,
                    linked: true,
                },
            ],
            productNumber: 'SW10000',
            name: 'Product 1',
            id: 'bc5ff49955be4b919053add552c2815d',
            childCount: 8,
            manufacturer: {
                name: 'Manufacturer A',
            },
        },
    ];

    // check if grid is sorting for currency
    const sortingForCurrency = criteria.sortings.some(sortAttr => sortAttr.field.startsWith('price'));

    if (sortingForCurrency) {
        const sortBy = criteria.sortings[0].field;
        const sortDirection = criteria.sortings[0].order;

        products.sort((productA, productB) => {
            const currencyId = sortBy.split('.')[1];

            const currencyValueA = productA.price.find(price => price.currencyId === currencyId).gross;
            const currencyValueB = productB.price.find(price => price.currencyId === currencyId).gross;

            if (sortDirection === 'DESC') {
                return currencyValueB - currencyValueA;
            }

            return currencyValueA - currencyValueB;
        });
    }

    // check if grid is sorting for name
    const sortingForName = criteria.sortings.some(sortAttr => sortAttr.field.startsWith('name'));

    if (sortingForName) {
        const sortDirection = criteria.sortings[0].order;
        products.sort((productA, productB) => {
            const nameA = productA.name.toLowerCase();
            const nameB = productB.name.toLowerCase();

            if (sortDirection === 'DESC') {
                return nameA > nameB ? -1 : 1;
            }

            return nameA < nameB ? -1 : 1;
        });
    }

    // check if grid is sorting for manufacturer name
    const sortingForManufacturer = criteria.sortings.some(sortAttr => sortAttr.field.startsWith('manufacturer'));

    if (sortingForManufacturer) {
        const sortDirection = criteria.sortings[0].order;
        products.sort((productA, productB) => {
            const nameA = productA.manufacturer.name.toLowerCase();
            const nameB = productB.manufacturer.name.toLowerCase();

            if (sortDirection === 'DESC') {
                return nameA > nameB ? -1 : 1;
            }

            return nameA < nameB ? -1 : 1;
        });
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
            id: CURRENCY_ID.EURO,
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
            id: CURRENCY_ID.POUND,
        },
    ];
}

async function createWrapper() {
    return {
        wrapper: mount(await wrapTestComponent('sw-advanced-selection-product', { sync: true }), {
            global: {
                provide: {
                    acl: {
                        can: () => true,
                    },
                    filterFactory: {
                        create: () => [],
                    },
                    filterService: {
                        getStoredCriteria: () => {
                            return Promise.resolve([]);
                        },
                        mergeWithStoredFilters: (storeKey, criteria) => criteria,
                    },
                    shortcutService: {
                        startEventListener() {
                        },
                        stopEventListener() {
                        },
                    },
                    numberRangeService: {},
                    repositoryFactory: {
                        create: (name) => {
                            if (name === 'product') {
                                return {
                                    search: (criteria) => {
                                        const productData = getProductData(criteria);

                                        return Promise.resolve(productData);
                                    },
                                };
                            }
                            if (name === 'user_config') {
                                return { search: () => Promise.resolve([]) };
                            }

                            return { search: () => Promise.resolve(getCurrencyData()) };
                        },
                    },
                    searchRankingService: {
                        getSearchFieldsByEntity: () => {
                            return Promise.resolve({
                                name: searchRankingPoint.HIGH_SEARCH_RANKING,
                            });
                        },
                        buildSearchQueriesForEntity: (searchFields, term, criteria) => {
                            return criteria;
                        },
                    },
                },
                stubs: {
                    'sw-entity-advanced-selection-modal': await wrapTestComponent('sw-entity-advanced-selection-modal'),
                    'sw-entity-listing': await wrapTestComponent('sw-entity-listing'),
                    'sw-modal': await wrapTestComponent('sw-modal'),
                    'sw-card': await wrapTestComponent('sw-card'),
                    'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                    'sw-card-filter': await wrapTestComponent('sw-card-filter'),
                    'sw-simple-search-field': {
                        template: '<div></div>',
                    },
                    'sw-context-button': {
                        template: '<div></div>',
                    },
                    'sw-context-menu-item': {
                        template: '<div></div>',
                    },
                    'sw-data-grid-settings': {
                        template: '<div></div>',
                    },
                    'sw-empty-state': {
                        template: '<div class="sw-empty-state"></div>',
                    },
                    'sw-pagination': {
                        template: '<div></div>',
                    },
                    'sw-icon': {
                        template: '<div></div>',
                    },
                    'router-link': true,
                    'sw-button': {
                        template: '<div></div>',
                    },
                    'sw-sidebar': {
                        template: '<div></div>',
                    },
                    'sw-sidebar-item': {
                        template: '<div></div>',
                    },
                    'sw-language-switch': {
                        template: '<div></div>',
                    },
                    'sw-notification-center': {
                        template: '<div></div>',
                    },
                    'sw-search-bar': {
                        template: '<div></div>',
                    },
                    'sw-loader': {
                        template: '<div></div>',
                    },
                    'sw-data-grid-skeleton': {
                        template: '<div class="sw-data-grid-skeleton"></div>',
                    },
                    'sw-checkbox-field': {
                        template: '<div></div>',
                    },
                    'sw-media-preview-v2': {
                        template: '<div></div>',
                    },
                    'sw-color-badge': {
                        template: '<div></div>',
                    },
                    'sw-extension-component-section': {
                        template: '<div></div>',
                    },
                    'sw-ignore-class': {
                        template: '<div></div>',
                    },
                    'sw-product-variant-info': true,
                    'sw-label': true,
                    'sw-filter-panel': true,
                    'sw-context-menu': true,
                    'sw-entity-advanced-selection-modal-grid': true,
                    'mt-card': true,
                    'sw-ai-copilot-badge': true,
                },
            },
        }),
    };
}

describe('components/sw-advanced-selection-product', () => {
    let wrapper;
    let selectionModal;

    beforeEach(async () => {
        const data = await createWrapper();
        wrapper = data.wrapper;

        await flushPromises();

        selectionModal = wrapper.getComponent({ name: 'sw-entity-advanced-selection-modal__wrapped' });
    });

    afterEach(() => {
        wrapper.unmount();
    });

    it('should be a Vue.JS component that wraps the selection modal component', async () => {
        expect(wrapper.vm).toBeTruthy();
        expect(selectionModal.exists()).toBe(true);
        expect(selectionModal.vm).toBeTruthy();
    });

    it('should return price when given currency id', async () => {
        const currencyId = CURRENCY_ID.EURO;
        const prices = mockPrices();

        const foundPriceData = wrapper.vm.getCurrencyPriceByCurrencyId(currencyId, prices);
        const expectedPriceData = {
            currencyId: CURRENCY_ID.EURO,
            net: 560.75,
            gross: 600,
            linked: true,
        };

        expect(foundPriceData).toEqual(expectedPriceData);
    });

    it('should return fallback when no price was found', async () => {
        const currencyId = 'no-valid-id';
        const prices = mockPrices();

        const foundPriceData = wrapper.vm.getCurrencyPriceByCurrencyId(currencyId, prices);
        const expectedPriceData = {
            currencyId: null,
            gross: null,
            linked: true,
            net: null,
        };

        expect(foundPriceData).toEqual(expectedPriceData);
    });

    it('should return false if product has no variants', async () => {
        const [product] = getProductData(mockCriteria());
        const productHasVariants = wrapper.vm.productHasVariants(product);

        expect(productHasVariants).toBe(false);
    });

    it('should return true if product has variants', async () => {
        const [, product] = getProductData(mockCriteria());
        const productHasVariants = wrapper.vm.productHasVariants(product);

        expect(productHasVariants).toBe(true);
    });

    it('should return filters from filter registry', () => {
        expect(wrapper.vm.currencyFilter).toEqual(expect.any(Function));
        expect(wrapper.vm.dateFilter).toEqual(expect.any(Function));
        expect(wrapper.vm.stockColorVariantFilter).toEqual(expect.any(Function));
    });
});
