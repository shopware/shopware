import template from './sw-product-list.twig';
import './sw-product-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-product-list', {
    template,

    inject: ['repositoryFactory', 'numberRangeService', 'acl'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('listing'),
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            products: null,
            currencies: [],
            sortBy: 'productNumber',
            sortDirection: 'DESC',
            naturalSorting: true,
            isLoading: false,
            isBulkLoading: false,
            total: 0,
            product: null,
            cloning: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
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
                    useCustomSort: true
                };
            });
        }
    },

    filters: {
        stockColorVariant(value) {
            if (value > 25) {
                return 'success';
            }

            if (value < 25 && value > 0) {
                return 'warning';
            }

            return 'error';
        }
    },

    methods: {
        getList() {
            this.isLoading = true;

            const productCriteria = new Criteria(this.page, this.limit);
            this.naturalSorting = this.sortBy === 'productNumber';

            productCriteria.setTerm(this.term);
            productCriteria.addFilter(Criteria.equals('product.parentId', null));
            productCriteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            productCriteria.addAssociation('cover');
            productCriteria.addAssociation('manufacturer');

            const currencyCriteria = new Criteria(1, 500);

            return Promise.all([
                this.productRepository.search(productCriteria, Shopware.Context.api),
                this.currencyRepository.search(currencyCriteria, Shopware.Context.api)
            ]).then((result) => {
                const products = result[0];
                const currencies = result[1];

                this.total = products.total;
                this.products = products;

                this.currencies = currencies;
                this.isLoading = false;
                this.selection = {};
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onInlineEditSave(promise, product) {
            const productName = product.name || this.placeholder(product, 'name');

            return promise.then(() => {
                this.createNotificationSuccess({
                    message: this.$tc('sw-product.list.messageSaveSuccess', 0, { name: productName })
                });
            }).catch(() => {
                this.getList();
                this.createNotificationError({
                    message: this.$tc('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid')
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

        getCurrencyPriceByCurrencyId(currencyId, prices) {
            const priceForProduct = prices.find(price => price.currencyId === currencyId);

            if (priceForProduct) {
                return priceForProduct;
            }

            return {
                currencyId: null,
                gross: null,
                linked: true,
                net: null
            };
        },

        getProductColumns() {
            return [{
                property: 'name',
                label: this.$tc('sw-product.list.columnName'),
                routerLink: 'sw.product.detail',
                inlineEdit: 'string',
                allowResize: true,
                primary: true
            }, {
                property: 'productNumber',
                naturalSorting: true,
                label: this.$tc('sw-product.list.columnProductNumber'),
                align: 'right',
                allowResize: true
            }, {
                property: 'manufacturer.name',
                label: this.$tc('sw-product.list.columnManufacturer'),
                allowResize: true
            }, {
                property: 'active',
                label: this.$tc('sw-product.list.columnActive'),
                inlineEdit: 'boolean',
                allowResize: true,
                align: 'center'
            },
            ...this.currenciesColumns,
            {
                property: 'stock',
                label: this.$tc('sw-product.list.columnInStock'),
                inlineEdit: 'number',
                allowResize: true,
                align: 'right'
            }, {
                property: 'availableStock',
                label: this.$tc('sw-product.list.columnAvailableStock'),
                allowResize: true,
                align: 'right'
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
            this.$refs.swProductGrid.loading = true;

            const context = Object.assign({}, Shopware.Context.api);
            context.currencyId = column.currencyId;

            return this.$refs.swProductGrid.repository.search(this.$refs.swProductGrid.items.criteria, context)
                .then(this.$refs.swProductGrid.applyResult);
        }
    }
});
