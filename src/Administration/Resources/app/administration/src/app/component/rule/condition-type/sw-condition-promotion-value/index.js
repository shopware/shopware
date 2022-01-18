import template from './sw-condition-promotion-value.html.twig';
import './sw-condition-promotion-value.scss';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Condition for the PromotionValueRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-promotion-value :condition="condition" :level="0"></sw-condition-promotion-value>
 */
Component.extend('sw-condition-promotion-value', 'sw-condition-base', {
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
