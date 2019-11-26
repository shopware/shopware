import template from './sw-condition-billing-street.html.twig';

const { Component } = Shopware;
const { mapApiErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Condition for the BillingStreetRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-billing-street :condition="condition" :level="0"></sw-condition-billing-street>
 */
Component.extend('sw-condition-billing-street', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('string');
        },

        streetName: {
            get() {
                this.ensureValueExist();
                return this.condition.value.streetName || '';
            },
            set(streetName) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, streetName };
            }
        },

        ...mapApiErrors('condition', ['value.operator', 'value.streetName']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueStreetNameError;
        }
    }
});
