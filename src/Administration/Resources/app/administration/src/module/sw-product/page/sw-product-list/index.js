import template from './sw-product-list.twig';
import './sw-product-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-product-list', {
    template,

    inject: ['repositoryFactory', 'numberRangeService'],

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
            criteriaArray: [],
            filterOptions: [
                {
                    name: 'onClearance',
                    label: this.$tc('sw-product.list.filter.clearanceSale.label'),
                    field: 'product.isCloseout',
                    inputType: 'switch',
                    criteriaType: 'equals'
                },
                {
                    name: 'activeInactive',
                    label: this.$tc('sw-product.list.filter.activeInactive.label'),
                    placeholder: this.$tc('sw-product.list.filter.activeInactive.placeholder'),
                    field: 'product.active',
                    inputType: 'singleSelect',
                    criteriaType: 'equals',
                    options: [
                        {
                            name: 'Active',
                            value: true
                        },
                        {
                            name: 'Inactive',
                            value: false
                        }
                    ]
                },
                {
                    name: 'manufacturer',
                    label: this.$tc('sw-product.list.filter.manufacturer.label'),
                    placeholder: this.$tc('sw-product.list.filter.manufacturer.placeholder'),
                    field: 'product.manufacturerId',
                    inputType: 'multiSelect',
                    criteriaType: 'equalsAny',
                    repository: 'product_manufacturer'
                },
                {
                    name: 'salesChannel',
                    label: this.$tc('sw-product.list.filter.salesChannel.label'),
                    placeholder: this.$tc('sw-product.list.filter.salesChannel.placeholder'),
                    field: 'product.visibilities.salesChannelId',
                    inputType: 'multiSelect',
                    criteriaType: 'equalsAny',
                    repository: 'sales_channel'
                },
                {
                    name: 'productNumber',
                    label: this.$tc('sw-product.list.filter.productNumber.label'),
                    placeholder: this.$tc('sw-product.list.filter.productNumber.placeholder'),
                    field: 'product.productNumber',
                    inputType: 'input',
                    criteriaType: 'contains'
                },
                {
                    name: 'price',
                    label: this.$tc('sw-product.list.filter.price.label'),
                    field: 'product.price',
                    inputType: 'range',
                    criteriaType: 'range'
                },
                {
                    name: 'stock',
                    label: this.$tc('sw-product.list.filter.stock.label'),
                    field: 'product.stock',
                    inputType: 'range',
                    criteriaType: 'range'
                }
            ]
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
                    dataIndex: `price-${item.id}`,
                    label: `${item.name}`,
                    routerLink: 'sw.product.detail',
                    allowResize: true,
                    visible: item.isSystemDefault,
                    align: 'right'
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
        updateCriteria(criteriaArray) {
            this.criteriaArray = criteriaArray;
            this.getList();
        },

        getList() {
            this.isLoading = true;

            const productCriteria = new Criteria(this.page, this.limit);
            this.naturalSorting = this.sortBy === 'productNumber';

            productCriteria.setTerm(this.term);
            productCriteria.addFilter(Criteria.equals('product.parentId', null));
            productCriteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            productCriteria.addAssociation('cover');
            productCriteria.addAssociation('manufacturer');

            this.criteriaArray.forEach((filter) => {
                productCriteria = productCriteria.addFilter(filter);
            });

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
                    title: this.$tc('sw-product.list.titleSaveSuccess'),
                    message: this.$tc('sw-product.list.messageSaveSuccess', 0, { name: productName })
                });
            }).catch(() => {
                this.getList();
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('global.notification.notificationSaveErrorMessage', 0, { entityName: productName })
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
            Shopware.StateDeprecated.getStore('language').setCurrentId(languageId);
            this.getList();
        },

        getCurrencyPriceByCurrencyId(itemId, currencyId) {
            let foundPrice = {
                currencyId: null,
                gross: null,
                linked: true,
                net: null
            };

            // check if products are loaded
            if (!this.products) {
                return foundPrice;
            }

            // find product for itemId
            const foundProduct = this.products.find((item) => {
                return item.id === itemId;
            });

            // find price from product with currency id
            if (foundProduct) {
                const priceForProduct = foundProduct.price.find((price) => {
                    return price.currencyId === currencyId;
                });

                if (priceForProduct) {
                    foundPrice = priceForProduct;
                }
            }

            // return the price
            return foundPrice;
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
            return this.numberRangeService.reserve('product').then((response) => {
                return this.productRepository.clone(referenceProduct.id, Shopware.Context.api, {
                    productNumber: response.number,
                    name: `${referenceProduct.name} ${this.$tc('sw-product.general.copy')}`,
                    productReviews: null,
                    active: false
                });
            }).then((duplicate) => {
                this.$router.push({ name: 'sw.product.detail', params: { id: duplicate.id } });
            });
        },

        duplicationDisabledTitle(product) {
            if (product.childCount > 0) {
                return this.$tc('sw-product.general.variantDuplication');
            }

            return '';
        }
    }
});
