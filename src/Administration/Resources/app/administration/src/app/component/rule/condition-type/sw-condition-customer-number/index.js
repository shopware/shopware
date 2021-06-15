import template from './sw-condition-billing-customer-number.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Condition for the CustomerNumberRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-customer-number :condition="condition" :level="0"></sw-condition-customer-number>
 */
Component.extend('sw-condition-customer-number', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('multiStore');
        },

        customerNumbers: {
            get() {
                this.ensureValueExist();
                return this.condition.value.numbers || [];
            },
            set(numbers) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, numbers };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.numbers']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueNumbersError;
        },
    },
});
