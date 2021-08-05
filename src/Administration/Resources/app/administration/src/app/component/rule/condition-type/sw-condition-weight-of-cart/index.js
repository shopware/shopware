import template from './sw-condition-weight-of-cart.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Condition for the CartWeightRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-weight-of-cart :condition="condition" :level="0"></sw-condition-weight-of-cart>
 */
Component.extend('sw-condition-weight-of-cart', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('number');
        },

        weight: {
            get() {
                this.ensureValueExist();
                return this.condition.value.weight;
            },
            set(weight) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, weight };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.weight']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueWeightError;
        },
    },
});
