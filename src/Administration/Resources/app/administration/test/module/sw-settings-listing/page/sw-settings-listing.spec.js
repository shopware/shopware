import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-listing/page/sw-settings-listing';

import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/grid/sw-pagination';

describe('src/module/sw-settings-listing/page/sw-settings-listing', () => {
    const testedSortingKey = 'tested-sorting-key';
    let wrapper;

    function getProductSortingEntities() {
        const entities = [
            {
                locked: false,
                key: 'name-asc',
                position: 1,
                active: true,
                fields: [
                    {
                        field: 'product.clearanceSale',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0
                    },
                    { field: 'product.name', order: 'asc', position: 1, naturalSorting: 0 },
                    {
                        field: 'product.ratingAverage',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0
                    },
                    {
                        field: 'product.number',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0
                    },
                    {
                        field: 'product.releaseDate',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0
                    },
                    {
                        field: 'product.stock',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0
                    },
                    {
                        field: 'product.unitsSold',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0
                    },
                    {
                        field: 'customFields.custom_health_hic_ut_aspernatur',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0
                    },
                    {
                        field: 'customFields.custom_movies_qui_aperiam_unde',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0
                    },
                    {
                        field: 'customFields.custom_tools_consequatur_omnis_officiis',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0
                    },
                    {
                        field: 'customFields.custom_tools_et_vel_nemo',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0
                    }
                ],
                label: 'Name',
                createdAt: '2020-08-07T13:38:24.098+00:00',
                updatedAt: '2020-08-10T06:19:28.071+00:00',
                translated: { label: 'Name' },
                apiAlias: null,
                id: '4f85a63f8ddd4845a67fd65adba419a2',
                translations: []
            },
            {
                locked: false,
                key: 'rating',
                position: 1,
                active: true,
                fields: [
                    {
                        field: 'product.ratingAverage',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0
                    }
                ],
                label: 'Rating',
                createdAt: '2020-08-07T13:38:42.798+00:00',
                updatedAt: null,
                translated: { label: 'Rating' },
                apiAlias: null,
                id: '5c34858ddac24af389f9315fc37709a3',
                translations: []
            },
            {
                locked: false,
                key: 'stock',
                position: 1,
                active: true,
                fields: [
                    {
                        field: 'product.stock',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0
                    }
                ],
                label: 'Stock',
                createdAt: '2020-08-07T13:39:38.853+00:00',
                updatedAt: '2020-08-07T13:55:24.732+00:00',
                translated: { label: 'Stock' },
                apiAlias: null,
                id: '6a2386b5bc394f25ac864a4bd4659a79',
                translations: []
            },
            {
                locked: false,
                key: 'product-number',
                position: 1,
                active: true,
                fields: [
                    {
                        field: 'product.productNumber',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0
                    }
                ],
                label: 'Product number',
                createdAt: '2020-08-07T13:38:58.730+00:00',
                updatedAt: '2020-08-07T13:55:12.005+00:00',
                translated: { label: 'Product number' },
                apiAlias: null,
                id: '6e1e3a71f6c443efb77ca68870759d72',
                translations: []
            },
            {
                locked: false,
                key: 'units-sold',
                position: 1,
                active: false,
                fields: [
                    {
                        field: 'product.unitsSold',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0
                    }
                ],
                label: 'Units sold',
                createdAt: '2020-08-07T13:40:21.490+00:00',
                updatedAt: null,
                translated: { label: 'Units sold' },
                apiAlias: null,
                id: '9385f6cab4f04d5fba70046e13c6ad45',
                translations: []
            },
            {
                locked: false,
                key: 'listing-price',
                position: 1,
                active: true,
                fields: [
                    {
                        field: 'product.cheapestPrice',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0
                    }
                ],
                label: 'Listing Price',
                createdAt: '2020-08-07T13:40:02.768+00:00',
                updatedAt: '2020-08-07T13:40:07.239+00:00',
                translated: { label: 'Listing Price' },
                apiAlias: null,
                id: 'c8c4ee0f193a431abe18d027ec3c95b2',
                translations: []
            },
            {
                locked: false,
                key: testedSortingKey,
                position: 1,
                active: false,
                fields: [
                    {
                        field: 'product.releaseDate',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0
                    }
                ],
                label: testedSortingKey,
                createdAt: '2020-08-10T06:19:44.820+00:00',
                updatedAt: null,
                translated: { label: testedSortingKey },
                apiAlias: null,
                id: 'cfa9d75ca8124da3ad83fc7a180fcc98',
                translations: []
            },
            {
                locked: true,
                key: 'release-date',
                position: 1,
                active: true,
                fields: [
                    {
                        field: 'product.releaseDate',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0
                    }
                ],
                label: 'Release Date',
                createdAt: '2020-08-07T13:39:18.605+00:00',
                updatedAt: null,
                translated: { label: 'Release Date' },
                apiAlias: null,
                id: 'd9df5fa7b8a7416a807b4d4c5cf13a69',
                translations: []
            },
            {
                locked: false,
                key: 'random-key',
                position: 1,
                active: false,
                fields: [
                    {
                        field: 'product.stock',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0
                    }
                ],
                label: 'random-key',
                createdAt: '2020-08-10T06:19:53.126+00:00',
                updatedAt: null,
                translated: { label: 'random-key' },
                apiAlias: null,
                id: 'e311624e917d4ed0b898231cb3a83bdf',
                translations: []
            },
            {
                locked: false,
                key: 'dont-care-key',
                position: 1,
                active: false,
                fields: [
                    {
                        field: 'product.stock',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0
                    }
                ],
                label: 'dont-care-key',
                createdAt: '2020-08-10T06:19:53.126+00:00',
                updatedAt: null,
                translated: { label: 'dont-care-key' },
                apiAlias: null,
                id: '23456787654321234567876588',
                translations: []
            },
            {
                locked: false,
                key: 'irrelevant-key',
                position: 1,
                active: false,
                fields: [
                    {
                        field: 'product.stock',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0
                    }
                ],
                label: 'irrelevant-key',
                createdAt: '2020-08-10T06:19:53.126+00:00',
                updatedAt: null,
                translated: { label: 'irrelevant-key' },
                apiAlias: null,
                id: '23456787654321234567876577',
                translations: []
            }
        ];

        entities.total = entities.length;

        return entities;
    }

    function createWrapper() {
        return shallowMount(Shopware.Component.build('sw-settings-listing'), {
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve(getProductSortingEntities())
                    })
                }
            },
            stubs: {
                'sw-page': {
                    template: '<div><slot name="content"></slot></div>'
                },
                'sw-system-config': {
                    data() {
                        return {
                            singleConfig: [true, true]
                        };
                    },
                    template: `
                        <div class="sw-system-config">
                            <div v-for="(config, index) in singleConfig">
                                <slot name="afterElements" v-bind="{ config, index }"></slot>
                            </div>
                        </div>
                    `
                },
                'sw-card-view': {
                    template: '<div><slot></slot></div>'
                },
                'sw-card': {
                    template: '<div><slot></slot></div>'
                },
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-data-grid': Shopware.Component.build('sw-data-grid'),
                'sw-empty-state': true,
                'sw-icon': true,
                'sw-pagination': Shopware.Component.build('sw-pagination'),
                'sw-single-select': true,
                'sw-settings-listing-default-sales-channel': true,
                'router-link': true
            }
        });
    }

    beforeEach(() => {
        wrapper = createWrapper();

        // sets the default sorting option
        wrapper.vm.$refs.systemConfig.actualConfigData = { null: { 'core.listing.defaultSorting': 'name-asc' } };
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have a pagination', async () => {
        const pagination = wrapper.find('.sw-pagination');

        expect(pagination.exists()).toBe(true);
    });

    it('should paginate', async () => {
        const pageButtons = wrapper.findAll('.sw-pagination .sw-pagination__list-button');
        const nextPageButton = pageButtons.wrappers[1];

        expect(wrapper.vm.sortingOptionsGridPage).toBe(1);

        await nextPageButton.trigger('click');

        expect(wrapper.vm.sortingOptionsGridPage).toBe(2);
    });

    it('should disable delete button when product sorting is default product sorting', async () => {
        const deleteButtonOfFirstRecord = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__actions-menu :last-child');

        expect(deleteButtonOfFirstRecord.attributes('disabled')).toBe('true');

        const deleteButtonOfSecondRecord = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__actions-menu :first-child');

        expect(deleteButtonOfSecondRecord.attributes('disabled')).toBe(undefined);
    });

    it('should check if product sorting is the default product sorting', async () => {
        // sets the default sorting option
        wrapper.vm.$refs.systemConfig.actualConfigData = { null: { 'core.listing.defaultSorting': 'price-asc' } };

        const isPriceDefaultSorting = wrapper.vm.isItemDefaultSorting('price-asc');

        expect(isPriceDefaultSorting).toBe(true);

        const isNameDefaultSorting = wrapper.vm.isItemDefaultSorting('name-desc');

        expect(isNameDefaultSorting).toBe(false);
    });

    it('should set inactive product sorting to be active when set as default sorting', async () => {
        let defaultSorting;

        const productSortings = wrapper.vm.productSortingOptions;

        Object.entries(productSortings).forEach(([, productSorting]) => {
            if (productSorting.key === testedSortingKey) {
                defaultSorting = productSorting;
            }
        });

        expect(defaultSorting).toBeDefined();
        expect(defaultSorting.active).toBeFalsy();

        // sets the default sorting option
        wrapper.vm.$refs.systemConfig.actualConfigData = { null: { 'core.listing.defaultSorting': testedSortingKey } };
        wrapper.vm.setDefaultSortingActive();

        expect(defaultSorting.active).toBeTruthy();
    });

    it('should render the default sorting selectbox only on first card', async () => {
        const defaultSortingSelects = wrapper.findAll('.sw-settings-listing-index__default-sorting-select');

        expect(defaultSortingSelects.length).toBe(1);
    });
});
