import { shallowMount } from '@vue/test-utils';
import uuid from 'src/../test/_helper_/uuid';
import 'src/module/sw-settings-listing/page/sw-settings-listing';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/grid/sw-pagination';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/select/entity/sw-entity-multi-select';
import 'src/app/component/form/select/entity/sw-entity-multi-id-select';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/select/base/sw-select-selection-list';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/base/sw-highlight-text';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/base/sw-label';

describe('src/module/sw-settings-listing/page/sw-settings-listing', () => {
    let defaultSalesChannelData = {};

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
                key: 'listing-prive',
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
                key: 'sadfsfad',
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
                label: 'sadfsfad',
                createdAt: '2020-08-10T06:19:44.820+00:00',
                updatedAt: null,
                translated: { label: 'sadfsfad' },
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
                key: 'asdfsafasdasss',
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
                label: 'asdfsaf',
                createdAt: '2020-08-10T06:19:53.126+00:00',
                updatedAt: null,
                translated: { label: 'asdfsaf' },
                apiAlias: null,
                id: 'e311624e917d4ed0b898231cb3a83bdf',
                translations: []
            },
            {
                locked: false,
                key: 'asdfsafasd',
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
                label: 'asdfsasdasdaaf',
                createdAt: '2020-08-10T06:19:53.126+00:00',
                updatedAt: null,
                translated: { label: 'asdfsaf' },
                apiAlias: null,
                id: '23456787654321234567876588',
                translations: []
            },
            {
                locked: false,
                key: 'random-test',
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
                label: 'asdfsasdasdaaf',
                createdAt: '2020-08-10T06:19:53.126+00:00',
                updatedAt: null,
                translated: { label: 'asdfsaf' },
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
                next5983: true,
                feature: {
                    isActive: () => true
                },
                repositoryFactory: {
                    create: (entity) => ({
                        search: () => {
                            if (entity === 'sales_channel') {
                                return Promise.resolve(createEntityCollection([
                                    {
                                        name: 'Storefront',
                                        translated: { name: 'Storefront' },
                                        id: uuid.get('storefront')
                                    },
                                    {
                                        name: 'Headless',
                                        translated: { name: 'Headless' },
                                        id: uuid.get('headless')
                                    }
                                ]));
                            }

                            return Promise.resolve(getProductSortingEntities());
                        }
                    })
                },
                systemConfigApiService: {
                    getConfig: () => Promise.resolve(),
                    getValues: () => Promise.resolve(defaultSalesChannelData)
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
</div>`
                },
                'sw-single-select': true,
                'sw-card-view': {
                    template: '<div><slot></slot></div>'
                },
                'sw-card': {
                    template: '<div><slot></slot></div>'
                },
                'sw-empty-state': {
                    template: '<div class="sw-empty-state"></div>'
                },
                'sw-data-grid': Shopware.Component.build('sw-data-grid'),
                'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'router-link': true,
                'sw-pagination': Shopware.Component.build('sw-pagination'),
                'sw-icon': true,
                'sw-field': true,
                'sw-container': true,
                'sw-entity-multi-select': Shopware.Component.build('sw-entity-multi-select'),
                'sw-entity-multi-id-select': Shopware.Component.build('sw-entity-multi-id-select'),
                'sw-switch-field': Shopware.Component.build('sw-switch-field'),
                'sw-select-base': Shopware.Component.build('sw-select-base'),
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-field-error': Shopware.Component.build('sw-field-error'),
                'sw-block-field': Shopware.Component.build('sw-block-field'),
                'sw-select-selection-list': Shopware.Component.build('sw-select-selection-list'),
                'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
                'sw-select-result': Shopware.Component.build('sw-select-result'),
                'sw-popover': Shopware.Component.build('sw-popover'),
                'sw-label': Shopware.Component.build('sw-label'),
                'sw-highlight-text': Shopware.Component.build('sw-highlight-text'),
                'sw-loader': true,
                'sw-modal': true,
                'sw-settings-listing-visibility-detail': true,
                'sw-button': true
            }
        });
    }

    function createEntityCollection(entities = []) {
        return new Shopware.Data.EntityCollection('sales_channel', 'sales_channel', {}, null, entities);
    }

    let wrapper;

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

        const defaultSortingKey = 'sadfsfad';
        const productSortings = wrapper.vm.productSortingOptions;

        Object.entries(productSortings).forEach(([, productSorting]) => {
            if (productSorting.key === defaultSortingKey) {
                defaultSorting = productSorting;
            }
        });

        expect(defaultSorting).toBeDefined();
        expect(defaultSorting.active).toBeFalsy();

        // sets the default sorting option
        wrapper.vm.$refs.systemConfig.actualConfigData = { null: { 'core.listing.defaultSorting': defaultSortingKey } };
        wrapper.vm.setDefaultSortingActive();

        expect(defaultSorting.active).toBeTruthy();
    });

    it('should render data correctly at Default Sales Channel card when there is no default sales channel data', async () => {
        const setVisibilityButton = wrapper.find('.sw-settings-listing-index__default-sales-channel-card .sw-card__quick-link');
        const activeSwitch = wrapper.find('.sw-settings-listing-index__default-sales-channel-card .sw-field--switch input');

        expect(activeSwitch.element.checked).toBe(true);
        expect(setVisibilityButton.exists()).toBeFalsy();
    });

    it('should render data correctly at Default Sales Channel card when there is default sales channel data', async () => {
        defaultSalesChannelData = {
            'core.defaultSalesChannel.active': false,
            'core.defaultSalesChannel.salesChannel': [{
                id: '98432def39fc4624b33213a56b8c944d',
                name: 'Headless'
            }],
            'core.defaultSalesChannel.visibility': { '98432def39fc4624b33213a56b8c944d': 10 }
        };

        wrapper = createWrapper();
        wrapper.vm.$refs.systemConfig.actualConfigData = { null: { 'core.listing.defaultSorting': 'name-asc' } };
        await wrapper.vm.$nextTick();

        const setVisibilityButton = wrapper.find('.sw-settings-listing-index__default-sales-channel-card .sw-card__quick-link');
        const activeSwitch = wrapper.find('.sw-settings-listing-index__default-sales-channel-card .sw-field--switch input');

        expect(activeSwitch.element.checked).toBe(false);
        expect(setVisibilityButton.exists()).toBeTruthy();
    });

    it('should display "Set visibility for selected Sales Channels" button when a sales channel is selected', async () => {
        const salesChannelCard = wrapper.find('.sw-settings-listing-index__default-sales-channel-card');

        salesChannelCard.find('.sw-select__selection').trigger('click');

        await salesChannelCard.find('input').trigger('change');
        await wrapper.vm.$nextTick();

        const list = wrapper.find('.sw-select-result-list__item-list').findAll('li');

        list.at(0).trigger('click');
        await wrapper.vm.$nextTick();

        expect(salesChannelCard.find('.sw-card__quick-link').exists()).toBeTruthy();
    });

    it('should display modal when clicking "Set visibility for selected Sales Channels" button', async () => {
        const salesChannelCard = wrapper.find('.sw-settings-listing-index__default-sales-channel-card');

        salesChannelCard.find('.sw-select__selection').trigger('click');

        await salesChannelCard.find('input').trigger('change');
        await wrapper.vm.$nextTick();

        const list = wrapper.find('.sw-select-result-list__item-list').findAll('li');

        list.at(0).trigger('click');
        await wrapper.vm.$nextTick();

        salesChannelCard.find('.sw-card__quick-link').trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-settings-listing-index__visibility-modal').exists()).toBeTruthy();
    });

    it('should render the default sorting selectbox only on first card', async () => {
        const defaultSortingSelects = wrapper.findAll('.sw-settings-listing-index__default-sorting-select');

        expect(defaultSortingSelects.length).toBe(1);
    });
});
