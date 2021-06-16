import template from './sw-settings-shipping-price-matrices.html.twig';
import './sw-settings-shipping-price-matrices.scss';

const { Component, Mixin, Data: { Criteria }, Context } = Shopware;
const { cloneDeep } = Shopware.Utils.object;
const { mapState, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('sw-settings-shipping-price-matrices', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
    ],

    props: {
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        ...mapState('swShippingDetail', [
            'shippingMethod',
        ]),

        ...mapGetters('swShippingDetail', [
            'shippingPriceGroups',
            'usedRules',
            'unrestrictedPriceMatrixExists',
            'newPriceMatrixExists',
        ]),

        ruleRepository() {
            return this.repositoryFactory.create('rule');
        },

        ruleFilter() {
            const criteria = new Criteria(1, 500);
            criteria.addFilter(Criteria.multi('OR', [
                Criteria.contains('rule.moduleTypes.types', 'price'),
                Criteria.equals('rule.moduleTypes', null),
            ]));
            return criteria;
        },

        shippingPriceRepository() {
            return this.repositoryFactory.create('shipping_method_price');
        },

        isLoaded() {
            return this.currencies.length && this.shippingMethod;
        },
    },

    methods: {
        onAddNewPriceGroup() {
            const newShippingPrice = this.shippingPriceRepository.create(Context.api);
            newShippingPrice.shippingMethodId = this.shippingMethod.id;
            newShippingPrice.quantityStart = 1;
            newShippingPrice.ruleId = null;

            // Create a flagged as new price matrix, if there is already an unrestricted.
            if (this.unrestrictedPriceMatrixExists) {
                // Flag to indicate that this price is in a new matrix
                newShippingPrice._inNewMatrix = true;
            }

            this.shippingMethod.prices.add(newShippingPrice);
        },

        onDeletePriceMatrix(shippingPriceGroup) {
            this.shippingMethod.prices = this.shippingMethod.prices.filter((shippingPrice) => {
                // If the shipping price group is new and the prices is also flagged new, remove it
                if (shippingPriceGroup.isNew) {
                    if (shippingPrice._inNewMatrix) {
                        return false;
                    }
                    return true;
                }

                return shippingPrice.ruleId !== shippingPriceGroup.ruleId;
            });
        },

        onDuplicatePriceMatrix(priceGroup) {
            const newPrices = [];
            priceGroup.prices.forEach(price => {
                const newShippingPrice = this.shippingPriceRepository.create(Context.api);
                // Create a flagged as new price matrix, if there is already an unrestricted.
                if (this.unrestrictedPriceMatrixExists) {
                    // Flag to indicate that this price is in a new matrix
                    newShippingPrice._inNewMatrix = true;
                }

                newShippingPrice.ruleId = null;
                newShippingPrice.calculation = price.calculation;
                newShippingPrice.calculationRule = price.calculationRule;
                newShippingPrice.calculationRuleId = price.calculationRuleId;
                newShippingPrice.shippingMethodId = price.shippingMethodId;
                newShippingPrice.currencyPrice = cloneDeep(price.currencyPrice);
                newShippingPrice.quantityStart = price.quantityStart;
                newShippingPrice.quantityEnd = price.quantityEnd;

                newPrices.push(newShippingPrice);
            });

            this.shippingMethod.prices.push(...newPrices);
        },
    },
});
