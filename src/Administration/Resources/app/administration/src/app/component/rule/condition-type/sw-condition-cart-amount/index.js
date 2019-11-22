import template from './sw-condition-cart-amount.html.twig';

const { Component } = Shopware;
const { mapApiErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Condition for the CartAmountRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-cart-amount :condition="condition" :level="0"></sw-condition-cart-amount>
 */
Component.extend('sw-condition-cart-amount', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('number');
        },
        amount: {
            get() {
                this.ensureValueExist();
                return this.condition.value.amount || null;
            },
            set(amount) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, amount };
            }
        },

        ...mapApiErrors('condition', ['value.operator', 'value.amount']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueAmountError;
        }
    }
});
