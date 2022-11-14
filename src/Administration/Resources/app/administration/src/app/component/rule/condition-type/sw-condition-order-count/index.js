import template from './sw-condition-order-count.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @package business-ops
 * @deprecated tag:v6.5.0 This rule component will be removed. Use sw-condition-generic instead.
 */
Component.extend('sw-condition-order-count', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('number');
        },

        count: {
            get() {
                this.ensureValueExist();
                return this.condition.value.count;
            },
            set(count) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, count };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.count']),

        currentError() {
            return this.conditionTypeError || this.conditionValueOperatorError || this.conditionValueCountError;
        },
    },
});
