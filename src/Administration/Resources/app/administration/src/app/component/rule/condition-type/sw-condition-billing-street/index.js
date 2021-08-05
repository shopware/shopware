import template from './sw-condition-billing-street.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

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

    data() {
        return {
            inputKey: 'streetName',
        };
    },

    computed: {
        operators() {
            return this.conditionDataProviderService.addEmptyOperatorToOperatorSet(
                this.conditionDataProviderService.getOperatorSet('string'),
            );
        },

        streetName: {
            get() {
                this.ensureValueExist();
                return this.condition.value.streetName || '';
            },
            set(streetName) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, streetName };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.streetName']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueStreetNameError;
        },
    },
});
