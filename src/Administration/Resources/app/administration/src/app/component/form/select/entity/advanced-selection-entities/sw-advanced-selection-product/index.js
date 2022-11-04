import template from './sw-advanced-selection-product.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 * @description Configures the advanced selection in entity selects.
 * Should only be used as a parameter `advanced-selection-component="sw-advanced-selection-product"`
 * to `sw-entity-...-select` components.
 * @status prototype
 */
Component.register('sw-advanced-selection-product', {
    template,

    inject: [
        'repositoryFactory',
    ],

    data() {
        return {
            currencies: [],
        };
    },

    computed: {
        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        productContext() {
            return { ...Shopware.Context.api, inheritance: true };
        },

        currenciesColumns() {
            return [...this.currencies].sort((a, b) => {
                return b.isSystemDefault ? 1 : -1;
            }).map(item => {
                return {
                    property: `price-${item.isoCode}`,
                    dataIndex: `price.${item.id}`,
                    label: `${item.name}`,
                    routerLink: 'sw.product.detail',
                    allowResize: true,
                    currencyId: item.id,
                    visible: item.isSystemDefault,
                    align: 'right',
                    useCustomSort: true,
                };
            });
        },

        productColumns() {
            return [
                {
                    property: 'name',
                    label: this.$tc('sw-product.list.columnName'),
                    routerLink: 'sw.product.detail',
                    inlineEdit: 'string',
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'productNumber',
                    naturalSorting: true,
                    label: this.$tc('sw-product.list.columnProductNumber'),
                    align: 'right',
                    allowResize: true,
                },
                {
                    property: 'manufacturer.name',
                    label: this.$tc('sw-product.list.columnManufacturer'),
                    allowResize: true,
                },
                {
                    property: 'active',
                    label: this.$tc('sw-product.list.columnActive'),
                    inlineEdit: 'boolean',
                    allowResize: true,
                    align: 'center',
                },
                ...this.currenciesColumns,
                {
                    property: 'stock',
                    label: this.$tc('sw-product.list.columnInStock'),
                    inlineEdit: 'number',
                    allowResize: true,
                    align: 'right',
                },
                {
                    property: 'availableStock',
                    label: this.$tc('sw-product.list.columnAvailableStock'),
                    allowResize: true,
                    align: 'right',
                },
                {
                    property: 'releaseDate',
                    label: this.$tc('sw-product.list.columnReleaseDate'),
                    allowResize: true,
                },
                {
                    property: 'visibilities',
                    dataIndex: 'visibilities.salesChannel',
                    label: this.$tc('sw-product.list.columnVisibilities'),
                    allowResize: true,
                    sortable: false,
                    visible: false,
                },
                {
                    property: 'categories',
                    label: this.$tc('sw-product.list.columnCategories'),
                    allowResize: true,
                    sortable: false,
                    visible: false,
                },
                {
                    property: 'tags',
                    label: this.$tc('sw-product.list.columnTags'),
                    allowResize: true,
                    sortable: false,
                    visible: false,
                },
            ];
        },

        productFilters() {
            return {
                'active-filter': {
                    property: 'active',
                    label: this.$tc('sw-product.filters.activeFilter.label'),
                    placeholder: this.$tc('sw-product.filters.activeFilter.placeholder'),
                },
                'stock-filter': {
                    property: 'stock',
                    label: this.$tc('sw-product.filters.stockFilter.label'),
                    numberType: 'int',
                    step: 1,
                    min: 0,
                    fromPlaceholder: this.$tc('sw-product.filters.fromPlaceholder'),
                    toPlaceholder: this.$tc('sw-product.filters.toPlaceholder'),
                },
                'product-without-images-filter': {
                    property: 'media',
                    label: this.$tc('sw-product.filters.imagesFilter.label'),
                    placeholder: this.$tc('sw-product.filters.imagesFilter.placeholder'),
                    optionHasCriteria: this.$tc('sw-product.filters.imagesFilter.textHasCriteria'),
                    optionNoCriteria: this.$tc('sw-product.filters.imagesFilter.textNoCriteria'),
                },
                'manufacturer-filter': {
                    property: 'manufacturer',
                    label: this.$tc('sw-product.filters.manufacturerFilter.label'),
                    placeholder: this.$tc('sw-product.filters.manufacturerFilter.placeholder'),
                },
                'visibilities-filter': {
                    property: 'visibilities.salesChannel',
                    label: this.$tc('sw-product.filters.salesChannelsFilter.label'),
                    placeholder: this.$tc('sw-product.filters.salesChannelsFilter.placeholder'),
                },
                'categories-filter': {
                    property: 'categories',
                    label: this.$tc('sw-product.filters.categoriesFilter.label'),
                    placeholder: this.$tc('sw-product.filters.categoriesFilter.placeholder'),
                    displayPath: true,
                },
                'sales-filter': {
                    property: 'sales',
                    label: this.$tc('sw-product.filters.salesFilter.label'),
                    digits: 20,
                    min: 0,
                    fromPlaceholder: this.$tc('sw-product.filters.fromPlaceholder'),
                    toPlaceholder: this.$tc('sw-product.filters.toPlaceholder'),
                },
                'price-filter': {
                    property: 'price',
                    label: this.$tc('sw-product.filters.priceFilter.label'),
                    digits: 20,
                    min: 0,
                    fromPlaceholder: this.$tc('sw-product.filters.fromPlaceholder'),
                    toPlaceholder: this.$tc('sw-product.filters.toPlaceholder'),
                },
                'tags-filter': {
                    property: 'tags',
                    label: this.$tc('sw-product.filters.tagsFilter.label'),
                    placeholder: this.$tc('sw-product.filters.tagsFilter.placeholder'),
                },
                'release-date-filter': {
                    property: 'releaseDate',
                    label: this.$tc('sw-product.filters.releaseDateFilter.label'),
                    dateType: 'datetime-local',
                    fromFieldLabel: null,
                    toFieldLabel: null,
                    showTimeframe: true,
                },
            };
        },

        productAssociations() {
            return [
                'cover',
                'media',
                'manufacturer',
                'options.group',
                'visibilities.salesChannel',
                'categories',
                'tags',
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.currencyRepository.search(new Criteria(1, 500)).then((currencies) => {
                this.currencies = currencies;
            });
        },

        productHasVariants(productEntity) {
            const childCount = productEntity.childCount;

            return childCount !== null && childCount > 0;
        },

        getCurrencyPriceByCurrencyId(currencyId, prices) {
            const priceForProduct = prices.find(price => price.currencyId === currencyId);

            if (priceForProduct) {
                return priceForProduct;
            }

            return {
                currencyId: null,
                gross: null,
                linked: true,
                net: null,
            };
        },

        getCategoryBreadcrumb(item) {
            if (item.breadcrumb) {
                return item.breadcrumb.join(' / ');
            }
            return item.translated.name || item.name;
        },
    },
});
