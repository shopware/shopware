import { Component, Mixin, State } from 'src/core/shopware';
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
            required: true,
            default: {}
        }
    },
    data() {
        return {
            itemAddNewRule: {
                index: -1,
                id: 'addNewRule'
            },
            showRuleModal: false,
            displayAdvancedPrices: false,
            displayAdvancedPricesLink: this.discount.type === DiscountTypes.ABSOLUTE,
            currencies: [],
            defaultCurrency: {},
            isLoading: false
        };
    },
    created() {
        this.createdComponent();
    },
    watch: {
        'discount.type': {
            handler() {
                this.verifyValueMax();
                this.showAdvancedPricesLink();
            }
        },
        'discount.value': {
            handler() {
                this.verifyValueMax();
            }
        }
    },
    computed: {
        rulesStore() {
            return State.getStore('rule');
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
        advancedPricesRepo() {
            return this.repositoryFactory.create('promotion_discount_prices');
        },
        currencyRepository() {
            return this.repositoryFactory.create('currency');
        }

    },
    methods: {
        createdComponent() {
            this.currencyRepository.search(new Criteria(), this.context).then((response) => {
                this.currencies = response;
                response.forEach((currency) => {
                    if (currency.isDefault) {
                        this.defaultCurrency = currency;
                    }
                });
            });
        },
        // Gets a list of all predefined scopes for the dropdown.
        // We use a method for this, because values are not translated when switching languages.
        // By using methods, they do get translated and reloaded correctly.
        getScopes() {
            return [
                { key: DiscountScopes.CART, name: this.$tc('sw-promotion.detail.main.discounts.valueScopeCart') }
            ];
        },
        // Gets a list of all predefined types for the dropdown.
        // We use a method for this, because values are not translated when switching languages.
        // By using methods, they do get translated and reloaded correctly.
        getTypes() {
            return [
                { key: DiscountTypes.ABSOLUTE, name: this.$tc('sw-promotion.detail.main.discounts.valueTypeAbsolute') },
                { key: DiscountTypes.PERCENTAGE, name: this.$tc('sw-promotion.detail.main.discounts.valueTypePercentage') }
            ];
        },
        getValueSuffix() {
            return discountHandler.getValueSuffix(this.discount.type);
        },
        getValueMin() {
            return discountHandler.getMinValue();
        },
        getValueMax() {
            return discountHandler.getMaxValue(this.discount.type);
        },
        // This function verifies the currently set value
        // depending on the discount type, and fixes it if
        // the min or maximum thresholds have been exceeded.
        verifyValueMax() {
            this.discount.value = discountHandler.getFixedValue(this.discount.value, this.discount.type);
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
        showAdvancedPricesLink() {
            this.displayAdvancedPricesLink = (this.discount.type === DiscountTypes.ABSOLUTE);
        },
        onSaveRule(rule) {
            this.$refs.productRuleSelector.addItem({ item: rule });
        },
        onOptionClick(event) {
            if (event.item.index === -1) {
                this.openCreateRuleModal();
            }
        },
        openCreateRuleModal() {
            this.showRuleModal = true;
        },
        onCloseRuleModal() {
            this.$refs.productRuleSelector.remove('addNewRule');
            this.showRuleModal = false;
        },
        openAdvancedPricesModal() {
            this.displayAdvancedPrices = true;
        },
        onCloseAdvancedPricesModal() {
            this.discount.promotionDiscountPrices.forEach((advancedPrice) => {
                const fixedPrice = discountHandler.getFixedValue(advancedPrice.price, DiscountTypes.ABSOLUTE);
                if (advancedPrice.price !== fixedPrice) {
                    advancedPrice.price = fixedPrice;
                }
            });
            this.displayAdvancedPrices = false;
        }
    }

});
