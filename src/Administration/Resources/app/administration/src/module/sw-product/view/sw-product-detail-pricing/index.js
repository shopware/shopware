/*
 * @package inventory
 */

import template from './sw-product-detail-pricing.html.twig';
import './sw-product-detail-pricing.scss';
import PricingService from "../../helper/sw-products-pricing-service";

const { Mixin } = Shopware;
const { Criteria, RepositoryLoader } = Shopware.Data;
const { mapState, mapGetters } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl', 'feature'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            errors: {},
            // prices: [],
            customerGroups: [],
            salesChannels: [],
            countries: [],
            isInherited: false,
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'currencies',
            'prices'
        ]),
        ...mapGetters('swProductDetail', [
            'isLoading',
            'defaultCurrency',
            'productTaxRate',
            'isChild',
        ]),

        isLoaded() {
            return !this.isLoading &&
                this.currencies &&
                this.product;
        },

        service() {
            return new PricingService(this.defaultCurrency);
        },

        repository() {
            return this.repositoryFactory.create('product_pricing');
        },

        columns() {
            const columns = [
                { property: 'line_number', label: '#', width: '20px', allowResize: true },
                { property: 'customerGroupId', label: 'Customer group', width: '200px', allowResize: true },
                { property: 'salesChannelId', label: 'Sales channel', width: '200px', allowResize: true },
                { property: 'countryId', label: 'Country', width: '200px', allowResize: true },
                { property: 'quantity', label: 'Quantity', width: '170px', allowResize: true },
                { property: 'discount', label: 'Discount', width: '170px', allowResize: true },
            ];

            return [...columns, ...this.currencyColumns];
        },

        currencyColumns() {
            this.sortCurrencies();

            return this.currencies.map((currency) => {
                return {
                    property: `price-${currency.isoCode}`,
                    label: currency.translated.name || currency.name,
                    visible: currency.isSystemDefault,
                    allowResize: true,
                    width: currency.isSystemDefault ? '270px' : '300px',
                    multiLine: true,
                };
            });
        },
    },

    watch: {
        prices() {
            this.validate();
        },
    },

    created() {
        if (this.prices.length === 0) {
            this.add(0);
        }
        this.loadDependencies();
    },

    methods: {
        copy(itemIndex) {
            const origin = this.prices[itemIndex];
            const copy = this.repository.create();

            copy.price = origin.price;
            copy.discount = origin.discount;

            if (origin.quantityEnd === null) {
                origin.quantityEnd = origin.quantityStart + 1;
            }

            copy.quantityStart = origin.quantityEnd + 1;
            copy.quantityEnd = null;
            copy.customerGroupId = origin.customerGroupId;
            copy.salesChannelId = origin.salesChannelId;
            copy.countryId = origin.countryId;

            this.prices.splice(itemIndex + 1, 0, copy);
        },

        add(index) {
            const record = this.repository.create();
            record.price = [
                { currencyId: this.defaultCurrency.id, gross: 0, linked: true, net: 0}
            ];
            record.discount = null;
            record.quantityStart = 1;
            record.quantityEnd = null;
            record.customerGroupId = null;
            record.salesChannelId = null;
            record.countryId = null;

            this.prices.splice(index + 1, 0, record);
        },

        remove(index) {
            if (index === 0) {
                return;
            }
            this.prices.splice(index, 1);
        },

        systemPrice(item) {
            if (item.price === null) {
                return { currencyId: this.defaultCurrency.id, gross: 0, linked: true, net: 0 };
            }
            return item.price.find((price) => price.currencyId === this.defaultCurrency.id);
        },

        isDefault(item) {
            return item.customerGroupId === null && item.salesChannelId === null && item.countryId === null;
        },

        onInheritanceRestore(item, currency) {
            // remove price from item.price with the currency id
            const indexOfPrice = item.price.findIndex((price) => price.currencyId === currency.id);
            item.price.splice(indexOfPrice, 1);
        },

        onInheritanceRemove(item, currency) {
            // create new price based on the default price
            const defaultPrice = this.systemPrice(item);

            if (!defaultPrice) {
                // add price to item.price
                this.$set(item.price, item.price.length, { currencyId: currency.id, gross: 0, linked: true, net: 0 });
                return;
            }

            const newPrice = {
                currencyId: currency.id,
                gross: this.convertPrice(defaultPrice.gross, currency),
                linked: defaultPrice.linked,
                net: this.convertPrice(defaultPrice.net, currency),
                listPrice: null,
            };

            if (defaultPrice.listPrice) {
                newPrice.listPrice = {
                    currencyId: currency.id,
                    gross: this.convertPrice(defaultPrice.listPrice.gross, currency),
                    linked: defaultPrice.listPrice.linked,
                    net: this.convertPrice(defaultPrice.listPrice.net, currency),
                };
            }

            // add price to item.price
            item.price.push(newPrice);
        },

        isPriceFieldInherited(item, currency) {
            return item.price.findIndex((price) => price.currencyId === currency.id) < 0;
        },

        convertPrice(value, currency) {
            const calculatedPrice = value * currency.factor;
            const priceRounded = calculatedPrice.toFixed(currency.decimalPrecision);
            return Number(priceRounded);
        },

        sortCurrencies() {
            this.currencies.sort((a, b) => {
                if (a.isSystemDefault) {
                    return -1;
                }
                if (b.isSystemDefault) {
                    return 1;
                }
                if (a.translated.name < b.translated.name) {
                    return -1;
                }
                if (a.translated.name > b.translated.name) {
                    return 1;
                }
                return 0;
            });
        },

        loadDependencies() {
            const promises = [];
            const loader = new RepositoryLoader();

            promises.push(loader.load(this.fieldCriteria(['id', 'name']), 'customer_group'));
            promises.push(loader.load(this.fieldCriteria(['id', 'name']), 'sales_channel'));
            promises.push(loader.load(this.fieldCriteria(['id', 'name']), 'country'));

            Promise.all(promises).then((response) => {
                response[0].forEach((item) => {
                    this.customerGroups.push({ value: item.id, label: item.translated.name })
                });
                response[1].forEach((item) => {
                    this.salesChannels.push({ value: item.id, label: item.translated.name })
                });
                response[2].forEach((item) => {
                    this.countries.push({ value: item.id, label: item.translated.name })
                });
            });
        },

        fieldCriteria(fields) {
            const criteria = new Criteria();
            criteria.fields = fields;
            return criteria;
        },

        col(property) {
            return {
                property: property,
                label: `sw-product.pricing.column.${property}`,
                visible: true,
            };
        },

        rowClass(classes, item, itemIndex) {
            const before = this.prices[itemIndex - 1] ?? null;
            if (itemIndex === 0) {
                return;
            }
            if (this.hash(item) !== this.hash(before)) {
                classes.push('changed');
            }
        },

        rowError(rowIndex) {
            if (!this.errors[rowIndex]) {
                return 'Valid';
            }
            return '<ul class="pricing-errors" style="padding: 10px">' + this.errors[rowIndex].join('\n') + '</ul>';
        },

        rowHasError(rowIndex) {
            return this.errors[rowIndex] && this.errors[rowIndex].length > 0;
        },

        sortPrices() {
            this.prices = this.service.sort(this.prices);
        },

        validate() {
            this.errors = this.service.validate(this.prices);
        },

        hash(price) {
            if (price === null) {
                return null;
            }
            return JSON.stringify([price.customerGroupId, price.salesChannelId, price.countryId]);
        }
    },
};
