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

    data() {
        return {
            inputKey: 'zipCodes',
        };
    },

    computed: {
        operators() {
            return this.conditionDataProviderService.addEmptyOperatorToOperatorSet(
                this.conditionDataProviderService.getOperatorSet('zipCode'),
            );
        },

        zipCodes: {
            get() {
                this.ensureValueExist();

                if (!this.condition.value.zipCodes) {
                    return this.isMultipleValues ? [] : null;
                }

                return this.isMultipleValues ? this.condition.value.zipCodes : Number(this.condition.value.zipCodes[0]);
            },
            set(zipCodes) {
                this.ensureValueExist();

                if (!Array.isArray(zipCodes)) {
                    zipCodes = [zipCodes.toString()];
                }

                this.condition.value = { ...this.condition.value, zipCodes };
            },
        },

        isMultipleValues() {
            this.ensureValueExist();
            return ['=', '!='].includes(this.condition.value.operator);
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.zipCodes']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueZipCodesError;
        },
    },
});
