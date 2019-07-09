import { Component, Mixin } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-promotion-discount-component.html.twig';
import './sw-promotion-discount-component.scss';
import DiscountTypes from './../../common/discount-type';
import DiscountScopes from './../../common/discount-scope';
import DiscountHandler from './handler';

const discountHandler = new DiscountHandler();

Component.register('sw-promotion-discount-component', {
    inject: ['repositoryFactory', 'context'],
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    props: {
        discount: {
            type: Object,
            required: true
        }
    },
    data() {
        return {
            displayAdvancedPrices: false,
            currencies: [],
            defaultCurrency: null,
            isLoading: false,
            showRuleModal: false,
            showDeleteModal: false
        };
    },
    created() {
        this.createdComponent();
    },

    computed: {
        advancedPricesRepo() {
            return this.repositoryFactory.create('promotion_discount_prices');
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        ruleFilter() {
            return Criteria.equalsAny(
                'conditions.type',
                [
                    'cartLineItem', 'cartLineItemTag', 'cartLineItemTotalPrice',
                    'cartLineItemUnitPrice', 'cartLineItemWithQuantity'
                ]
            );
        },

        currencyPriceColumns() {
            return [{
                property: 'currency.translated.name',
                label: this.$tc('sw-promotion.detail.main.discounts.pricesModal.labelCurrency')
            }, {
                property: 'price',
                dataIndex: 'price',
                label: this.$tc('sw-promotion.detail.main.discounts.pricesModal.labelPrice')
            }];
        },

        scopes() {
            return [
                { key: DiscountScopes.CART, name: this.$tc('sw-promotion.detail.main.discounts.valueScopeCart') }
            ];
        },

        types() {
            return [
                { key: DiscountTypes.ABSOLUTE, name: this.$tc('sw-promotion.detail.main.discounts.valueTypeAbsolute') },
                { key: DiscountTypes.PERCENTAGE, name: this.$tc('sw-promotion.detail.main.discounts.valueTypePercentage') }
            ];
        },

        valueSuffix() {
            return discountHandler.getValueSuffix(
                this.discount.type,
                this.defaultCurrency !== null ? this.defaultCurrency.suffix : null
            );
        },

        showAdvancedPricesLink() {
            return this.discount.type === DiscountTypes.ABSOLUTE;
        }

    },
    methods: {
        createdComponent() {
            this.currencyRepository.search(new Criteria(), this.context).then((response) => {
                this.currencies = response;
                this.defaultCurrency = this.currencies.find(currency => currency.isDefault);
            });
        },

        // This function verifies the currently set value
        // depending on the discount type, and fixes it if
        // the min or maximum thresholds have been exceeded.
        verifyValueMax() {
            this.discount.value = discountHandler.getFixedValue(this.discount.value, this.discount.type);
        },

        changeValue(value) {
            this.discount.value = discountHandler.getFixedValue(value, this.discount.type);
        },

        onClickAdvancedPrices() {
            this.currencies.forEach((currency) => {
                if (!this.isMemberOfCollection(currency)) {
                    this.prepareAdvancedPrices(currency, this.discount.value);
                }
            });
            this.displayAdvancedPrices = true;
        },

        prepareAdvancedPrices(currency, basePrice) {
            // first get the minimum value that is allowed
            let setPrice = discountHandler.getMinValue();
            // if basePrice is undefined take the minimum price
            if (basePrice !== undefined) {
                setPrice = basePrice;
            }
            // foreign currencies are translated at the exchange rate of the default currency
            setPrice *= currency.factor;
            // even if translated correctly the value may not be less than the allowed minimum value
            if (setPrice < discountHandler.getMinValue()) {
                setPrice = discountHandler.getMinValue();
            }
            // now create the value with the calculated and translated value
            const newAdvancedCurrencyPrices = this.advancedPricesRepo.create(this.context);
            newAdvancedCurrencyPrices.discountId = this.discount.id;
            newAdvancedCurrencyPrices.price = setPrice;
            newAdvancedCurrencyPrices.currencyId = currency.id;
            newAdvancedCurrencyPrices.currency = currency;
            this.discount.promotionDiscountPrices.add(newAdvancedCurrencyPrices);
        },

        isMemberOfCollection(currency) {
            let foundValue = false;
            const currencyID = currency.id;
            this.discount.promotionDiscountPrices.forEach((advancedPrice) => {
                if (advancedPrice.currencyId === currencyID) {
                    foundValue = true;
                    advancedPrice.currency = currency;
                }
            });
            return foundValue;
        },

        onCloseAdvancedPricesModal() {
            this.discount.promotionDiscountPrices.forEach((advancedPrice) => {
                const fixedPrice = discountHandler.getFixedValue(advancedPrice.price, DiscountTypes.ABSOLUTE);
                if (advancedPrice.price !== fixedPrice) {
                    advancedPrice.price = fixedPrice;
                }
            });
            this.displayAdvancedPrices = false;
        },

        onShowDeleteModal() {
            this.showDeleteModal = true;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete() {
            this.onCloseDeleteModal();
            this.$nextTick(() => {
                this.$emit('discount-delete', this.discount);
            });
        }
    }
});
