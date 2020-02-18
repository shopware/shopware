import template from './sw-condition-shipping-zip-code.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Condition for the ShippingZipCodeRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-shipping-zip-code :condition="condition" :level="0"></sw-condition-shipping-zip-code>
 */
Component.extend('sw-condition-shipping-zip-code', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('multiStore');
        },

        zipCodes: {
            get() {
                this.ensureValueExist();
                return this.condition.value.zipCodes || [];
            },
            set(zipCodes) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, zipCodes };
            }
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.zipCodes']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueZipCodesError;
        }
    }
});
