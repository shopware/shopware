import { mount } from '@vue/test-utils';

describe('src/module/sw-settings-listing/page/sw-settings-listing', () => {
    const notificationMixinMock = {
        methods: {
            createNotificationError: jest.fn(),
            createNotificationSuccess: jest.fn(),
        },
    };
    const testedSortingId = 'cfa9d75ca8124da3ad83fc7a180fcc98';
    let wrapper;

    function getProductSortingEntities() {
        const entities = [
            {
                locked: false,
                key: 'name-asc',
                value: 'name-asc',
                position: 1,
                active: true,
                fields: [
                    {
                        field: 'product.clearanceSale',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0,
                    },
                    { field: 'product.name', order: 'asc', position: 1, naturalSorting: 0 },
                    {
                        field: 'product.ratingAverage',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0,
                    },
                    {
                        field: 'product.number',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0,
                    },
                    {
                        field: 'product.releaseDate',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0,
                    },
                    {
                        field: 'product.stock',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0,
                    },
                    {
                        field: 'product.unitsSold',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0,
                    },
                    {
                        field: 'customFields.custom_health_hic_ut_aspernatur',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0,
                    },
                    {
                        field: 'customFields.custom_movies_qui_aperiam_unde',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0,
                    },
                    {
                        field: 'customFields.custom_tools_consequatur_omnis_officiis',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0,
                    },
                    {
                        field: 'customFields.custom_tools_et_vel_nemo',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0,
                    },
                ],
                label: 'Name',
                createdAt: '2020-08-07T13:38:24.098+00:00',
                updatedAt: '2020-08-10T06:19:28.071+00:00',
                translated: { label: 'Name' },
                apiAlias: null,
                id: '4f85a63f8ddd4845a67fd65adba419a2',
                translations: [],
            },
            {
                locked: false,
                key: 'rating',
                value: 'rating',
                position: 1,
                active: true,
                fields: [
                    {
                        field: 'product.ratingAverage',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0,
                    },
                ],
                label: 'Rating',
                createdAt: '2020-08-07T13:38:42.798+00:00',
                updatedAt: null,
                translated: { label: 'Rating' },
                apiAlias: null,
                id: '5c34858ddac24af389f9315fc37709a3',
                translations: [],
            },
            {
                locked: false,
                key: 'stock',
                value: 'stock',
                position: 1,
                active: true,
                fields: [
                    {
                        field: 'product.stock',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0,
                    },
                ],
                label: 'Stock',
                createdAt: '2020-08-07T13:39:38.853+00:00',
                updatedAt: '2020-08-07T13:55:24.732+00:00',
                translated: { label: 'Stock' },
                apiAlias: null,
                id: '6a2386b5bc394f25ac864a4bd4659a79',
                translations: [],
            },
            {
                locked: false,
                key: 'product-number',
                value: 'product-number',
                position: 1,
                active: true,
                fields: [
                    {
                        field: 'product.productNumber',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0,
                    },
                ],
                label: 'Product number',
                createdAt: '2020-08-07T13:38:58.730+00:00',
                updatedAt: '2020-08-07T13:55:12.005+00:00',
                translated: { label: 'Product number' },
                apiAlias: null,
                id: '6e1e3a71f6c443efb77ca68870759d72',
                translations: [],
            },
            {
                locked: false,
                key: 'units-sold',
                value: 'units-sold',
                position: 1,
                active: false,
                fields: [
                    {
                        field: 'product.unitsSold',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0,
                    },
                ],
                label: 'Units sold',
                createdAt: '2020-08-07T13:40:21.490+00:00',
                updatedAt: null,
                translated: { label: 'Units sold' },
                apiAlias: null,
                id: '9385f6cab4f04d5fba70046e13c6ad45',
                translations: [],
            },
            {
                locked: false,
                key: 'listing-price',
                value: 'listing-price',
                position: 1,
                active: true,
                fields: [
                    {
                        field: 'product.cheapestPrice',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0,
                    },
                ],
                label: 'Listing Price',
                createdAt: '2020-08-07T13:40:02.768+00:00',
                updatedAt: '2020-08-07T13:40:07.239+00:00',
                translated: { label: 'Listing Price' },
                apiAlias: null,
                id: 'c8c4ee0f193a431abe18d027ec3c95b2',
                translations: [],
            },
            {
                locked: false,
                key: 'tested-sorting-key',
                value: 'tested-sorting-key',
                position: 1,
                active: false,
                fields: [
                    {
                        field: 'product.releaseDate',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0,
                    },
                ],
                label: 'tested-sorting-key',
                createdAt: '2020-08-10T06:19:44.820+00:00',
                updatedAt: null,
                translated: { label: 'tested-sorting-key' },
                apiAlias: null,
                id: testedSortingId,
                translations: [],
            },
            {
                locked: true,
                key: 'release-date',
                value: 'release-date',
                position: 1,
                active: true,
                fields: [
                    {
                        field: 'product.releaseDate',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0,
                    },
                ],
                label: 'Release Date',
                createdAt: '2020-08-07T13:39:18.605+00:00',
                updatedAt: null,
                translated: { label: 'Release Date' },
                apiAlias: null,
                id: 'd9df5fa7b8a7416a807b4d4c5cf13a69',
                translations: [],
            },
            {
                locked: false,
                key: 'random-key',
                value: 'random-key',
                position: 1,
                active: false,
                fields: [
                    {
                        field: 'product.stock',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0,
                    },
                ],
                label: 'random-key',
                createdAt: '2020-08-10T06:19:53.126+00:00',
                updatedAt: null,
                translated: { label: 'random-key' },
                apiAlias: null,
                id: 'e311624e917d4ed0b898231cb3a83bdf',
                translations: [],
            },
            {
                locked: false,
                key: 'dont-care-key',
                value: 'dont-care-key',
                position: 1,
                active: false,
                fields: [
                    {
                        field: 'product.stock',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0,
                    },
                ],
                label: 'dont-care-key',
                createdAt: '2020-08-10T06:19:53.126+00:00',
                updatedAt: null,
                translated: { label: 'dont-care-key' },
                apiAlias: null,
                id: '23456787654321234567876588',
                translations: [],
            },
            {
                locked: false,
                key: 'irrelevant-key',
                value: 'irrelevant-key',
                position: 1,
                active: false,
                fields: [
                    {
                        field: 'product.stock',
                        order: 'asc',
                        position: 1,
                        naturalSorting: 0,
                    },
                ],
                label: 'irrelevant-key',
                createdAt: '2020-08-10T06:19:53.126+00:00',
                updatedAt: null,
                translated: { label: 'irrelevant-key' },
                apiAlias: null,
                id: '23456787654321234567876577',
                translations: [],
            },
        ];

        entities.total = entities.length;

        return entities;
    }

    const customFields = [
        {
            name: 'custom_health_hic_ut_aspernatur',
            config: {
                label: 'custom health hic ut aspernatur',
            },
        },
        {
            name: 'custom_movies_qui_aperiam_unde',
            config: {
                label: 'custom movies qui aperiam unde',
            },
        },
        {
            name: 'custom_tools_consequatur_omnis_officiis',
            config: {
                label: 'custom tools consequatur omnis officiis',
            },
        },
        {
            name: 'custom_tools_et_vel_nemo',
            config: {
                label: 'custom tools et vel nemo',
            },
        },
    ];

    const snippets = {
        'sw-settings-listing.general.productSortingCriteriaGrid.options.label.product.name': 'Product name',
        'sw-settings-listing.general.productSortingCriteriaGrid.options.label.product.price': 'Product price',
        'sw-settings-listing.general.productSortingCriteriaGrid.options.label.product.unitsSold': 'Units sold',
        'sw-settings-listing.general.productSortingCriteriaGrid.options.label.product.stock': 'Stock',
        'sw-settings-listing.general.productSortingCriteriaGrid.options.label.product.releaseDate': 'Release date',
        'sw-settings-listing.general.productSortingCriteriaGrid.options.label.product.number': 'Number',
        'sw-settings-listing.general.productSortingCriteriaGrid.options.label.product.ratingAverage': 'Rating Average',
        'sw-settings-listing.general.productSortingCriteriaGrid.options.label.product.clearanceSale': 'Clearance sale',
    };

    async function createWrapper() {
        return mount(await wrapTestComponent('sw-settings-listing', {
            sync: true,
        }), {
            global: {
                renderStubDefaultSlot: true,
                provide: {
                    repositoryFactory: {
                        create: (name) => {
                            if (name === 'product_sorting') {
                                return {
                                    search: () => Promise.resolve(getProductSortingEntities()),
                                    saveAll: () => Promise.resolve(),
                                    delete: () => Promise.resolve(),
                                };
                            }
                            if (name === 'system_config') {
                                return {
                                    search: () => Promise.resolve(getProductSortingEntities()),
                                    delete: () => Promise.resolve(),
                                };
                            }
                            if (name === 'custom_field') {
                                return {
                                    search: () => Promise.resolve(customFields),
                                };
                            }
                            return { search: () => Promise.resolve(getProductSortingEntities()) };
                        },
                    },
                    systemConfigApiService: {
                        batchSave: () => {},
                    },
                },
                mixins: [
                    notificationMixinMock,
                    Shopware.Mixin.getByName('sw-inline-snippet'),
                ],
                stubs: {
                    'sw-page': {
                        template: '<div><slot name="smart-bar-actions"></slot><slot name="content"></slot></div>',
                    },
                    'sw-system-config': {
                        data() {
                            return {
                                singleConfig: [true, true],
                                actualConfigData: {
                                    null: {
                                        'core.listing.defaultSorting': 'name-asc',
                                    },
                                },
                                currentSalesChannelId: null,
                            };
                        },
                        computed: {
                            isNotDefaultSalesChannel() {
                                return this.currentSalesChannelId !== null;
                            },
                        },
                        methods: {
                            saveAll() {

                            },
                            onSalesChannelChanged(salesChannelId) {
                                this.currentSalesChannelId = salesChannelId;
                                if (!this.actualConfigData[salesChannelId]) {
                                    this.$set(this.actualConfigData, this.currentSalesChannelId, {});
                                }
                            },
                        },
                        template: `
                            <div class="sw-system-config">
                                <div v-for="(config, index) in singleConfig">
                                    <slot name="afterElements" v-bind="{ config: actualConfigData[currentSalesChannelId], index, isNotDefaultSalesChannel, inheritance: actualConfigData.null }"></slot>
                                </div>
                            </div>
                        `,
                    },
                    'sw-sales-channel-switch': true,
                    'sw-card-view': {
                        template: '<div class=""><slot></slot></div>',
                    },
                    'sw-card': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-context-button': true,
                    'sw-button-process': {
                        template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>',
                    },
                    'sw-context-menu-item': {
                        template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>',
                    },
                    'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                    'sw-empty-state': true,
                    'sw-icon': true,
                    'sw-pagination': await wrapTestComponent('sw-pagination'),
                    'sw-single-select': await wrapTestComponent('sw-single-select'),
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-settings-listing-default-sales-channel': {
                        methods: {
                            saveSalesChannelVisibilityConfig() {
                                Promise.resolve();
                            },
                        },
                        template: `
                        <div class="sw-settings-listing-default-sales-channel">
                        </div>
                    `,
                    },
                    'router-link': true,
                    'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper'),
                    'sw-inheritance-switch': {
                        props: {
                            isInherited: {
                                type: Boolean,
                                required: true,
                                default: false,
                            },
                        },
                        template: `
                            <div
                                class="sw-inheritance-switch"
                                :class="{
                                    'sw-inheritance-switch--is-inherited': isInherited,
                                    'sw-inheritance-switch--is-not-inherited': !isInherited,
                                }"
                            >
                                <button
                                    v-if="isInherited"
                                    @click="$emit('inheritance-remove')"
                                />
                                <button
                                    v-else
                                    @click="$emit('inheritance-restore')"
                                />
                            </div>
                        `,
                    },
                    'sw-settings-listing-delete-modal': {
                        template: `
                            <div
                                class="sw-settings-listing-delete-modal">
                                <button variant="danger" @click="$emit('delete')"/>
                            </div>
                        `,
                    },
                    'sw-skeleton': true,
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                    'sw-popover': {
                        template: `
                            <div class="sw-popover"><slot></slot></div>
                        `,
                    },
                    'sw-select-result': await wrapTestComponent('sw-select-result'),
                },
                mocks: {
                    $tc: (param) => {
                        if (snippets[param]) {
                            return snippets[param];
                        }
                        return param;
                    },
                },
            },
        });
    }

    beforeEach(async () => {
        wrapper = await createWrapper();

        // sets the default sorting option
        wrapper.vm.$refs.systemConfig.actualConfigData = { null: { 'core.listing.defaultSorting': '4f85a63f8ddd4845a67fd65adba419a2' } };

        await flushPromises();
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
        const nextPageButton = pageButtons[1];

        expect(wrapper.vm.sortingOptionsGridPage).toBe(1);

        await nextPageButton.trigger('click');

        expect(wrapper.vm.sortingOptionsGridPage).toBe(2);
    });

    it('should disable delete button when product sorting is default product sorting', async () => {
        const deleteButtonOfFirstRecord = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__actions-menu :last-child');

        expect(deleteButtonOfFirstRecord.attributes('disabled')).toBeDefined();

        const deleteButtonOfSecondRecord = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__actions-menu :first-child');

        expect(deleteButtonOfSecondRecord.attributes('disabled')).toBeUndefined();
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
            if (productSorting.id === testedSortingId) {
                defaultSorting = productSorting;
            }
        });

        expect(defaultSorting).toBeDefined();
        expect(defaultSorting.active).toBeFalsy();

        // sets the default sorting option
        wrapper.vm.$refs.systemConfig.actualConfigData = { null: { 'core.listing.defaultSorting': testedSortingId } };
        wrapper.vm.setDefaultSortingActive();

        expect(defaultSorting.active).toBeTruthy();
    });

    it('should render the default sorting select box only on first card', async () => {
        const defaultSortingSelects = wrapper.findAll('.sw-settings-listing-index__default-sorting-select');

        expect(defaultSortingSelects).toHaveLength(1);
    });

    it('should render the default sorting select box in an inherited wrapper', async () => {
        const defaultSortingSelect = wrapper.find('.sw-inherit-wrapper .sw-settings-listing-index__default-sorting-select');

        expect(defaultSortingSelect).not.toBeNull();
    });

    it('should show an error notification on save when the default sorting in "all sales channels" is empty', async () => {
        const defaultSortingSelectInput = wrapper.find('.sw-inherit-wrapper .sw-settings-listing-index__default-sorting-select input');

        await defaultSortingSelectInput.trigger('click');

        await defaultSortingSelectInput.setValue(null);
        await defaultSortingSelectInput.trigger('input');

        await defaultSortingSelectInput.trigger('keydown.esc');

        await wrapper.find('.sw-settings-listing__save-action').trigger('click');
        await flushPromises();

        expect(wrapper.findAll('.sw-inherit-wrapper .sw-settings-listing-index__default-sorting-select.has--error')).toHaveLength(1);
    });

    it('should show a success notification on save when the default sorting in "all sales channels" is filled', async () => {
        await wrapper.find('.sw-settings-listing__save-action').trigger('click');
        await flushPromises();

        expect(wrapper.findAll('.sw-inherit-wrapper .sw-settings-listing-index__default-sorting-select.has--error')).toHaveLength(0);
    });

    it('should restore inheritance when the selected default sorting was deleted', async () => {
        wrapper.vm.$refs.systemConfig.onSalesChannelChanged('salesChannelId');
        await flushPromises();

        const defaultSortingInheritWrapper = wrapper.find('.sw-inherit-wrapper');

        const defaultSortingToggleInheritance = defaultSortingInheritWrapper.find('.sw-inheritance-switch button');
        await defaultSortingToggleInheritance.trigger('click');
        await flushPromises();

        const defaultSortingSelectInput = wrapper.find('.sw-inherit-wrapper .sw-settings-listing-index__default-sorting-select input');
        await defaultSortingSelectInput.trigger('click');
        await flushPromises();

        await wrapper.find('.sw-inherit-wrapper .sw-settings-listing-index__default-sorting-select .sw-select-option--rating').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-settings-listing__save-action').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-data-grid__row--1 > .sw-data-grid__cell--actions button:last-child').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-settings-listing-delete-modal button').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-settings-listing__save-action').trigger('click');
        await flushPromises();

        expect(wrapper.vm.$refs.systemConfig.actualConfigData.salesChannelId['core.listing.defaultSorting']).toBeNull();
        expect(defaultSortingInheritWrapper.attributes('class')).toContain('is--inherited');
        expect(defaultSortingInheritWrapper.findAll('.sw-settings-listing-index__default-sorting-select.has--error')).toHaveLength(0);
    });

    it('should display correct product sorting criteria', async () => {
        expect(wrapper.find('.sw-data-grid__row--0 > .sw-data-grid__cell--criteria > div > span').text())
            .toBe('Clearance sale, Product name, Rating Average, Number, Release date, Stock, Units sold, ' +
                'custom health hic ut aspernatur, custom movies qui aperiam unde, ' +
                'custom tools consequatur omnis officiis, custom tools et vel nemo');
    });
});
