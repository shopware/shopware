import template from './sw-condition-cart-position-price.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @deprecated tag:v6.5.0 This rule component will be removed. Use sw-condition-generic instead.
 * @public
 * @description Condition for the CartPositionPriceRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-cart-position-price :condition="condition" :level="0"></sw-condition-cart-position-price>
 */
Component.extend('sw-condition-cart-position-price', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('number');
        },

        amount: {
            get() {
                this.ensureValueExist();
                return this.condition.value.amount;
            },
            set(amount) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, amount };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.amount']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueAmountError;
        },
    },
});
