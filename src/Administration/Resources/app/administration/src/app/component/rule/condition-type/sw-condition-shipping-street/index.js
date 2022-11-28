import template from './sw-condition-shipping-street.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @deprecated tag:v6.5.0 This rule component will be removed. Use sw-condition-generic instead.
 * @public
 * @package business-ops
 * @description Condition for the ShippingStreetRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-shipping-street :condition="condition" :level="0"></sw-condition-shipping-street>
 */
Component.extend('sw-condition-shipping-street', 'sw-condition-base', {
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
                return this.condition.value.streetName;
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
