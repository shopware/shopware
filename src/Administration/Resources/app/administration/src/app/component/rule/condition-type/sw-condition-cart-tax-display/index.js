import template from './sw-condition-cart-tax-display.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Condition for the CartTaxDisplay. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-cart-tax-display :condition="condition" :level="0"></sw-condition-cart-tax-display>
 */
Component.extend('sw-condition-cart-tax-display', 'sw-condition-base', {
    template,

    computed: {
        selectValues() {
            return [
                {
                    label: this.$tc('global.sw-condition.condition.cartTaxDisplay.gross'),
                    value: 'gross',
                },
                {
                    label: this.$tc('global.sw-condition.condition.cartTaxDisplay.net'),
                    value: 'net',
                },
            ];
        },

        taxDisplay: {
            get() {
                this.ensureValueExist();
                return this.condition.value.taxDisplay;
            },
            set(taxDisplay) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, taxDisplay };
            },
        },

        ...mapPropertyErrors('condition', ['value.taxDisplay']),

        currentError() {
            return this.conditionValueTaxDisplayError;
        },
    },
});
