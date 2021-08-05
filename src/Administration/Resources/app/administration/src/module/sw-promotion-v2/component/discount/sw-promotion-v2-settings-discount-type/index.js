import template from './sw-promotion-v2-settings-discount-type.html.twig';
import './sw-promotion-v2-settings-discount-type.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-promotion-v2-settings-discount-type', {
    template,

    inject: [
        'acl',
        'repositoryFactory',
    ],

    props: {
        discount: {
            type: Object,
            required: true,
        },

        discountScope: {
            type: String,
            required: true,
            validator(value) {
                return ['basic', 'buy-x-get-y', 'shipping-discount'].includes(value);
            },
        },

        preselectedDiscountType: {
            type: String,
            required: false,
            validator(value) {
                return ['fixed', 'fixed_unit', 'percentage', 'free'].includes(value);
            },
            default() {
                return 'fixed';
            },
        },

        preselectedApplyDiscountTo: {
            type: String,
            required: false,
            validator(value) {
                return ['ALL', 'SELECT'].includes(value);
            },
            default() {
                return 'ALL';
            },
        },
    },

    data() {
        return {
            displayAdvancedPricesModal: false,
            currencies: [],
            defaultCurrency: null,
            currencySymbol: null,
        };
    },

    computed: {
        isPercentageType() {
            return ['percentage', 'free'].includes(this.discount.type);
        },

        labelValue() {
            return this.$tc(
                'sw-promotion-v2.detail.discounts.settings.discountType.labelValue',
                !this.isPercentageType,
            );
        },

        showAdvancedPricesLink() {
            return ['absolute', 'fixed', 'fixed_unit'].includes(this.discount.type);
        },

        currencyPriceColumns() {
            return [{
                property: 'currency.translated.name',
                label: this.$tc('sw-promotion-v2.detail.discounts.pricesModal.labelCurrency'),
            }, {
                property: 'price',
                dataIndex: 'price',
                label: this.$tc('sw-promotion-v2.detail.discounts.pricesModal.labelPrice'),
            }];
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        advancedPricesRepo() {
            return this.repositoryFactory.create('promotion_discount_prices');
        },

        currencyCriteria() {
            return (new Criteria())
                .addSorting(Criteria.sort('name', 'ASC'));
        },

        showMaxValueAdvancedPrices() {
            return this.discount.type === 'percentage' && this.discount.maxValue !== null;
        },
    },

    watch: {
        'discount.type'(value, oldValue) {
            if (oldValue === 'percentage') {
                this.discount.maxValue = null;
            }

            if (value === 'free') {
                this.discount.applierKey = 'SELECT';
                this.discount.value = 100;

                return;
            }

            if (value === 'absolute') {
                this.discount.applierKey = 'SELECT';
                this.discount.usageKey = 'ALL';
            } else if (value === 'percentage') {
                this.discount.value = Math.min(this.discount.value, 100);
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.currencyRepository.search(this.currencyCriteria).then((response) => {
                this.currencies = response;

                this.defaultCurrency = this.currencies.find(currency => currency.isSystemDefault);
                this.currencySymbol = this.defaultCurrency.symbol;
            });

            if (!this.discount.isNew) {
                return;
            }

            let config = {
                type: this.discount.type || this.preselectedDiscountType,
                applierKey: this.discount.applierKey || this.preselectedApplyDiscountTo,
            };

            if (this.discountScope === 'basic') {
                config = {
                    ...config,
                    scope: 'cart',
                };
            } else if (this.discountScope === 'buy-x-get-y') {
                config = {
                    ...config,
                    scope: 'set',
                };
            } else if (this.discountScope === 'shipping-discount') {
                config = {
                    ...config,
                    scope: 'delivery',
                };
            }

            Object.assign(this.discount, config);
        },

        getDiscountTypeSelection() {
            const prefix = 'sw-promotion-v2.detail.discounts.settings.discountType.discountTypeSelection';
            return [{
                value: 'percentage',
                display: this.$tc(`${prefix}.displayPercentage`),
            }, {
                value: (this.discount.scope === 'delivery' ? 'absolute' : 'fixed'),
                display: this.$tc(`${prefix}.displayFixedDiscount`),
            }, {
                value: 'fixed_unit',
                display: this.$tc(`${prefix}.displayFixedPrice`),
            }, {
                value: 'free',
                display: this.$tc(`${prefix}.displayFree`),
            }];
        },

        getApplyDiscountToSelection() {
            const prefix = 'sw-promotion-v2.detail.discounts.settings.discountType.applyDiscountTo';

            return [{
                value: 'ALL',
                display: this.$tc(`${prefix}.displayTotalPrice`),
            }, {
                value: 'SELECT',
                display: this.$tc(`${prefix}.displayProductPrice`),
            }];
        },

        onClickAdvancedPrices() {
            this.currencies.forEach((currency) => {
                if (!this.setCurrencyForDiscountPrices(currency)) {
                    if (this.showMaxValueAdvancedPrices) {
                        this.prepareAdvancedPrices(currency, this.discount.maxValue);
                    } else {
                        this.prepareAdvancedPrices(currency, this.discount.value);
                    }
                }
            });

            this.displayAdvancedPricesModal = true;
        },

        clearAdvancedPrices() {
            const ids = this.discount.promotionDiscountPrices.getIds();

            ids.forEach((id) => {
                this.discount.promotionDiscountPrices.remove(id);
            });
        },

        setCurrencyForDiscountPrices(currency) {
            const currencyId = currency.id;
            return this.discount.promotionDiscountPrices.some((advancedPrice) => {
                if (advancedPrice.currencyId === currencyId) {
                    advancedPrice.currency = currency;
                    return true;
                }
                return false;
            });
        },

        prepareAdvancedPrices(currency, basePrice = 0.0) {
            const setPrice = Math.max(basePrice * currency.factor, 0.0);

            const newAdvancedCurrencyPrices = this.advancedPricesRepo.create();
            Object.assign(newAdvancedCurrencyPrices, {
                discountId: this.discount.id,
                price: setPrice,
                currencyId: currency.id,
                currency: currency,
            });

            this.discount.promotionDiscountPrices.add(newAdvancedCurrencyPrices);
        },

        onMaxValueChanged(value) {
            if (value !== null && value !== 0) {
                return;
            }

            if (value === 0) {
                this.discount.maxValue = null;
            }

            this.clearAdvancedPrices();
        },

        onCloseAdvancedPricesModal() {
            if (this.discount.type === 'percentage' && this.discount.maxValue === null) {
                this.clearAdvancedPrices();
                this.displayAdvancedPricesModal = false;

                return;
            }

            this.discount.promotionDiscountPrices.forEach((advancedPrice) => {
                if (this.discount.type === 'percentage') {
                    advancedPrice.price = (advancedPrice.price > 100) ?
                        this.getMaxValue(this.discount.type) :
                        advancedPrice.price;
                }

                if (advancedPrice.price <= 0.00) {
                    advancedPrice.price = 0.00;
                }

                advancedPrice.price = Math.max(advancedPrice.price, 0.0);
            });

            this.displayAdvancedPricesModal = false;
        },
    },
});
