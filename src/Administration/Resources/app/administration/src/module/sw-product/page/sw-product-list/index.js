import template from './sw-product-list.html.twig';
import './sw-product-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-product-list', {
    template,

    inject: [
        'feature',
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
        return {
            products: null,
            currencies: [],
            sortBy: 'productNumber',
            sortDirection: 'DESC',
            naturalSorting: false,
            isLoading: false,
            isBulkLoading: false,
            total: 0,
            product: null,
            cloning: false,
            productEntityVariantModal: false,
            filterCriteria: [],
            defaultFilters: [
                'active-filter',
                'product-without-images-filter',
                'release-date-filter',
                'stock-filter',
                'price-filter',
                'manufacturer-filter',
                'visibilities-filter',
                'categories-filter',
                'tags-filter',
            ],
            storeKey: 'grid.filter.product',
            activeFilterNumber: 0,
            showBulkEditModal: false,
        };
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
            // eslint-disable-next-line vue/no-side-effects-in-computed-properties
            return this.currencies.sort((a, b) => {
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

        productCriteria() {
            const productCriteria = new Criteria(this.page, this.limit);

            productCriteria.setTerm(this.term);
            productCriteria.addFilter(Criteria.equals('product.parentId', null));
            productCriteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            productCriteria.addAssociation('cover');
            productCriteria.addAssociation('manufacturer');
            productCriteria.addAssociation('media');

            this.filterCriteria.forEach(filter => {
                productCriteria.addFilter(filter);
            });

            return productCriteria;
        },

        currencyCriteria() {
            return new Criteria(1, 500);
        },

        showVariantModal() {
            return !!this.productEntityVariantModal;
        },

        listFilters() {
            return this.filterFactory.create('product', {
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
                },
            });
        },

        productBulkEditColumns() {
            return this.productColumns.map(item => {
                const { inlineEdit, ...restParams } = item;
                return restParams;
            });
        },
    },

    watch: {
        productCriteria: {
            handler() {
                this.getList();
            },
            deep: true,
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

            const criteria = await Shopware.Service('filterService')
                .mergeWithStoredFilters(this.storeKey, this.productCriteria);

            this.activeFilterNumber = criteria.filters.length - 1;

            try {
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

            return promise.then(() => {
                this.createNotificationSuccess({
                    message: this.$tc('sw-product.list.messageSaveSuccess', 0, { name: productName }),
                });
            }).catch(() => {
                this.getList();
                this.createNotificationError({
                    message: this.$tc('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
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
            Shopware.State.commit('context/setApiLanguageId', languageId);
            this.getList();
        },

        updateCriteria(criteria) {
            this.page = 1;

            this.filterCriteria = criteria;
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

        getProductColumns() {
            return [{
                property: 'name',
                label: this.$tc('sw-product.list.columnName'),
                routerLink: 'sw.product.detail',
                inlineEdit: 'string',
                allowResize: true,
                primary: true,
            }, {
                property: 'productNumber',
                naturalSorting: true,
                label: this.$tc('sw-product.list.columnProductNumber'),
                align: 'right',
                allowResize: true,
            }, {
                property: 'manufacturer.name',
                label: this.$tc('sw-product.list.columnManufacturer'),
                allowResize: true,
            }, {
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
            }, {
                property: 'availableStock',
                label: this.$tc('sw-product.list.columnAvailableStock'),
                allowResize: true,
                align: 'right',
            }];
        },

        onDuplicate(referenceProduct) {
            this.product = referenceProduct;
            this.cloning = true;
        },

        onDuplicateFinish(duplicate) {
            this.cloning = false;
            this.product = null;

            this.$nextTick(() => {
                this.$router.push({ name: 'sw.product.detail', params: { id: duplicate.id } });
            });
        },

        onColumnSort(column) {
            this.onSortColumn(column);
        },

        productHasVariants(productEntity) {
            const childCount = productEntity.childCount;

            return childCount !== null && childCount > 0;
        },

        openVariantModal(item) {
            this.productEntityVariantModal = item;
        },

        closeVariantModal() {
            this.productEntityVariantModal = null;
        },

        onBulkEditItems() {
            this.$router.push({ name: 'sw.bulk.edit.product' });
        },

        onBulkEditModalOpen() {
            this.showBulkEditModal = true;
        },

        onBulkEditModalClose() {
            this.showBulkEditModal = false;
        },
    },
});
