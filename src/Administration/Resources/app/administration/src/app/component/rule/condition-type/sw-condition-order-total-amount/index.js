import template from './sw-condition-order-total-amount.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Condition for the OrderTotalAmountRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-order-total-amount :condition="condition" :level="0"></sw-condition-order-total-amount>
 */
Component.extend('sw-condition-order-total-amount', 'sw-condition-base', {
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
