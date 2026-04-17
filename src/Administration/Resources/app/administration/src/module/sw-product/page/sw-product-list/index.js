/*
 * @sw-package inventory
 */

import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import template from './sw-product-list.html.twig';
import './sw-product-list.scss';

const { Mixin, Context } = Shopware;
const { Criteria } = Shopware.Data;
const { cloneDeep } = Shopware.Utils.object;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'numberRangeService',
        'acl',
        'filterFactory',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('listing'),
        Mixin.getByName('placeholder'),
    ],

    data() {
        const data = {
            products: null,
            currencies: [],
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            naturalSorting: true,
            isLoading: false,
            isBulkLoading: false,
            total: 0,
            product: null,
            cloning: false,
            productEntityVariantModal: false,
            filterCriteria: [],
            // @deprecated tag:v6.8.0 - Will be removed
            productTypeOptions: [
                {
                    label: this.$t('sw-product.type.physical'),
                    value: 'physical',
                },
                {
                    label: this.$t('sw-product.type.digital'),
                    value: 'digital',
                },
            ],
            defaultFilters: [
                'product-number-filter',
                'active-filter',
                'product-without-images-filter',
                'release-date-filter',
                'stock-filter',
                'price-filter',
                'manufacturer-filter',
                'visibilities-filter',
                'categories-filter',
                'sales-filter',
                'tags-filter',
                'product-states-filter',
                'product-type-filter',
            ],
            storeKey: 'grid.filter.product',
            activeFilterNumber: 0,
            showBulkEditModal: false,
            searchConfigEntity: 'product',
        };

        if (Shopware.Feature.isActive('v6.8.0.0')) {
            data.defaultFilters = data.defaultFilters.filter((filter) => filter !== 'product-states-filter');
        }

        return data;
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        },

        productColumns() {
            return this.getProductColumns();
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        currenciesColumns() {
            return this.currencies
                .toSorted((a, b) => {
                    return b.isSystemDefault ? 1 : -1;
                })
                .map((item) => {
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

        productCriteria() {
            const productCriteria = new Criteria(this.page, this.limit);

            productCriteria.setTerm(this.term);
            productCriteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            productCriteria.addAssociation('cover.media');
            productCriteria.addAssociation('manufacturer');
            productCriteria.addAssociation('tax');

            this.filterCriteria.forEach((filter) => {
                productCriteria.addFilter(filter);
            });

            return productCriteria;
        },

        currencyCriteria() {
            return new Criteria(1, 500);
        },

        salesChannelCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addSorting(Criteria.sort('name'));

            return criteria;
        },

        showVariantModal() {
            return !!this.productEntityVariantModal;
        },

        listFilterOptions() {
            const filters = {
                'product-number-filter': {
                    property: 'productNumber',
                    type: 'string-filter',
                    label: this.$t('sw-product.filters.productNumberFilter.label'),
                    placeholder: this.$t('sw-product.filters.productNumberFilter.placeholder'),
                    valueProperty: 'key',
                    labelProperty: 'key',
                    criteriaFilterType: this.adminEsEnable ? 'equals' : 'contains',
                },
                'active-filter': {
                    property: 'active',
                    label: this.$t('sw-product.filters.activeFilter.label'),
                    placeholder: this.$t('sw-product.filters.activeFilter.placeholder'),
                },
                'stock-filter': {
                    property: 'stock',
                    label: this.$t('sw-product.filters.stockFilter.label'),
                    numberType: 'int',
                    step: 1,
                    fromPlaceholder: this.$t('sw-product.filters.fromPlaceholder'),
                    toPlaceholder: this.$t('sw-product.filters.toPlaceholder'),
                },
                'product-without-images-filter': {
                    property: 'media',
                    label: this.$t('sw-product.filters.imagesFilter.label'),
                    placeholder: this.$t('sw-product.filters.imagesFilter.placeholder'),
                    optionHasCriteria: this.$t('sw-product.filters.imagesFilter.textHasCriteria'),
                    optionNoCriteria: this.$t('sw-product.filters.imagesFilter.textNoCriteria'),
                },
                'manufacturer-filter': {
                    property: 'manufacturer',
                    label: this.$t('sw-product.filters.manufacturerFilter.label'),
                    placeholder: this.$t('sw-product.filters.manufacturerFilter.placeholder'),
                },
                'visibilities-filter': {
                    property: 'visibilities.salesChannel',
                    label: this.$t('sw-product.filters.salesChannelsFilter.label'),
                    placeholder: this.$t('sw-product.filters.salesChannelsFilter.placeholder'),
                    criteria: this.salesChannelCriteria,
                },
                'categories-filter': {
                    property: 'categories',
                    label: this.$t('sw-product.filters.categoriesFilter.label'),
                    placeholder: this.$t('sw-product.filters.categoriesFilter.placeholder'),
                    displayPath: true,
                },
                'sales-filter': {
                    property: 'sales',
                    label: this.$t('sw-product.filters.salesFilter.label'),
                    digits: 20,
                    min: 0,
                    fromPlaceholder: this.$t('sw-product.filters.fromPlaceholder'),
                    toPlaceholder: this.$t('sw-product.filters.toPlaceholder'),
                },
                'price-filter': {
                    property: 'price',
                    label: this.$t('sw-product.filters.priceFilter.label'),
                    digits: 20,
                    min: 0,
                    fromPlaceholder: this.$t('sw-product.filters.fromPlaceholder'),
                    toPlaceholder: this.$t('sw-product.filters.toPlaceholder'),
                },
                'tags-filter': {
                    property: 'tags',
                    label: this.$t('sw-product.filters.tagsFilter.label'),
                    placeholder: this.$t('sw-product.filters.tagsFilter.placeholder'),
                },
                'product-states-filter': {
                    property: 'states',
                    label: this.$t('sw-product.filters.productStatesFilter.label'),
                    placeholder: this.$t('sw-product.filters.productStatesFilter.placeholder'),
                    type: 'multi-select-filter',
                    options: [
                        {
                            label: this.$t('sw-product.filters.productStatesFilter.options.physical'),
                            value: 'is-physical',
                        },
                        {
                            label: this.$t('sw-product.filters.productStatesFilter.options.digital'),
                            value: 'is-download',
                        },
                    ],
                },
                'product-type-filter': {
                    property: 'type',
                    label: this.$t('sw-product.filters.productTypeFilter.label'),
                    placeholder: this.$t('sw-product.filters.productTypeFilter.placeholder'),
                    type: 'multi-select-filter',
                    options: this.productTypes.map((type) => ({
                        label: this.$t(`sw-product.type.${type}`),
                        value: type,
                    })),
                },
                'release-date-filter': {
                    property: 'releaseDate',
                    label: this.$t('sw-product.filters.releaseDateFilter.label'),
                    dateType: 'datetime-local',
                    fromFieldLabel: null,
                    toFieldLabel: null,
                    showTimeframe: true,
                },
            };

            if (Shopware.Feature.isActive('v6.8.0.0')) {
                delete filters['product-states-filter'];
            }

            return filters;
        },

        listFilters() {
            return this.filterFactory.create('product', this.listFilterOptions);
        },

        productBulkEditColumns() {
            return this.productColumns.map((item) => {
                const { inlineEdit, ...restParams } = item;
                return restParams;
            });
        },

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },

        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, because the filter is unused
         */
        dateFilter() {
            return Shopware.Filter.getByName('date');
        },

        stockColorVariantFilter() {
            return Shopware.Filter.getByName('stockColorVariant');
        },

        adminEsEnable() {
            if (!Shopware.Feature.isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
                return false;
            }

            return Context.app.adminEsEnable ?? false;
        },

        productTypes() {
            return [
                'physical',
                'digital',
            ];
        },
    },

    beforeRouteLeave(to, from, next) {
        const goingToProductDetailPage = to.name === 'sw.product.detail.base';

        if (goingToProductDetailPage && this.showVariantModal) {
            this.closeVariantModal();
        }

        this.$nextTick(() => {
            next();
        });
    },

    methods: {
        async getList() {
            this.isLoading = true;

            let criteria = await Shopware.Service('filterService').mergeWithStoredFilters(
                this.storeKey,
                this.productCriteria,
            );
            criteria.filters = this.normalizeCategoryFilters(criteria.filters);

            if (!criteria.filters.some((filter) => filter.field === 'type')) {
                criteria.addPostFilter(Criteria.equalsAny('type', this.productTypes));
            }

            if (this.adminEsEnable) {
                criteria.setTerm(this.term);
            } else {
                criteria = await this.addQueryScores(this.term, criteria);
            }

            // Clone product query to its variant
            const variantCriteria = cloneDeep(criteria);
            criteria.addFilter(Criteria.equals('product.parentId', null));
            variantCriteria.addFilter(
                Criteria.not('AND', [
                    Criteria.equals('product.parentId', null),
                ]),
            );

            this.activeFilterNumber = criteria.filters.length - 1;

            if (!this.entitySearchable) {
                this.isLoading = false;
                this.total = 0;

                return;
            }

            if (this.freshSearchTerm) {
                criteria.resetSorting();
            }

            try {
                if (this.term) {
                    const variants = await this.productRepository.search(variantCriteria, {
                        ...Context.api,
                        inheritance: true,
                    });
                    if (variants.length > 0) {
                        const parentIds = [];

                        variants.forEach((variant) => {
                            parentIds.push(variant.parentId);
                        });

                        criteria.addQuery(Criteria.equalsAny('id', parentIds), searchRankingPoint.HIGH_SEARCH_RANKING);
                    }
                }

                const result = await Promise.all([
                    this.productRepository.search(criteria),
                    this.currencyRepository.search(this.currencyCriteria),
                ]);

                const products = result[0];
                const currencies = result[1];

                this.total = products.total;
                this.products = products;

                this.currencies = currencies;
                this.isLoading = false;

                this.selection = {};
            } catch {
                this.isLoading = false;
            }
        },

        onInlineEditSave(promise, product) {
            const productName = product.name || this.placeholder(product, 'name');

            return promise
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$t('sw-product.list.messageSaveSuccess', { name: productName }, 0),
                    });
                })
                .catch(() => {
                    this.getList();
                    this.createNotificationError({
                        message: this.$t('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
                    });
                });
        },

        onInlineEditCancel(product) {
            product.discardChanges();
        },

        updateTotal({ total }) {
            this.total = total;
        },

        onChangeLanguage(languageId) {
            Shopware.Store.get('context').setApiLanguageId(languageId);
            this.getList();
        },

        updateCriteria(criteria) {
            return Mixin.getByName('listing').methods.updateCriteria.call(this, this.normalizeCategoryFilters(criteria));
        },

        normalizeCategoryFilters(filters) {
            return filters.map((filter) => {
                if (filter.field !== 'categories.id') {
                    return filter;
                }

                const categoryIds = Array.isArray(filter.value) ? filter.value : filter.value.split('|');
                if (categoryIds.length === 0) {
                    return filter;
                }

                return Criteria.multi('OR', [
                    filter,
                    Criteria.equalsAny('product.streams.categories.id', categoryIds),
                ]);
            });
        },

        getCurrencyPriceByCurrencyId(currencyId, prices) {
            const priceForProduct = prices.find((price) => price.currencyId === currencyId);

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

        getProductColumns() {
            return [
                {
                    property: 'name',
                    label: this.$t('sw-product.list.columnName'),
                    routerLink: 'sw.product.detail',
                    inlineEdit: 'string',
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'productNumber',
                    naturalSorting: this.naturalSorting,
                    label: this.$t('sw-product.list.columnProductNumber'),
                    align: 'right',
                    allowResize: true,
                },
                {
                    property: 'manufacturer.name',
                    label: this.$t('sw-product.list.columnManufacturer'),
                    allowResize: true,
                },
                {
                    property: 'active',
                    label: this.$t('sw-product.list.columnActive'),
                    inlineEdit: 'boolean',
                    allowResize: true,
                    align: 'center',
                },
                {
                    property: 'sales',
                    label: this.$t('sw-product.list.columnSales'),
                    allowResize: true,
                    align: 'right',
                },
                ...this.currenciesColumns,
                {
                    property: 'stock',
                    label: this.$t('sw-product.list.columnInStock'),
                    inlineEdit: 'number',
                    allowResize: true,
                    align: 'right',
                },
                {
                    property: 'availableStock',
                    label: this.$t('sw-product.list.columnAvailableStock'),
                    allowResize: true,
                    align: 'right',
                },
                {
                    property: 'createdAt',
                    label: this.$t('sw-product.list.columnCreatedAt'),
                    allowResize: true,
                },
                {
                    property: 'updatedAt',
                    label: this.$t('sw-product.list.columnUpdatedAt'),
                    allowResize: true,
                    visible: false,
                },
            ];
        },

        onDuplicate(referenceProduct) {
            this.product = referenceProduct;
            this.cloning = true;
        },

        onDuplicateFinish(duplicate) {
            this.cloning = false;
            this.product = null;

            this.$nextTick(() => {
                this.$router.push({
                    name: 'sw.product.detail',
                    params: { id: duplicate.id },
                });
            });
        },

        onColumnSort(column) {
            this.onSortColumn(column);
        },

        productHasVariants(productEntity) {
            const childCount = productEntity.childCount;

            return childCount !== null && childCount > 0;
        },

        productIsDigital(productEntity) {
            return productEntity.type && productEntity.type === 'digital';
        },

        openVariantModal(item) {
            this.productEntityVariantModal = item;
        },

        closeVariantModal() {
            this.productEntityVariantModal = null;
        },

        onBulkEditItems() {
            let includesDigital = '0';
            const digital = Object.values(this.selection).filter((product) => product.type === 'digital');
            if (digital.length > 0) {
                includesDigital = digital.filter((product) => product.isCloseout).length !== digital.length ? '1' : '2';
            }

            this.$router.push({
                name: 'sw.bulk.edit.product',
                params: {
                    parentId: 'null',
                    includesDigital,
                },
            });
        },

        onBulkEditModalOpen() {
            this.showBulkEditModal = true;
        },

        onBulkEditModalClose() {
            this.showBulkEditModal = false;
        },
    },
};
