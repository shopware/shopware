import template from './sw-condition-line-item-product-states.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @deprecated tag:v6.5.0 This rule component will be removed. Use sw-condition-generic-line-item instead.
 * @public
 * @description Condition for the LineItemProductStatesRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-line-item-product-states :condition="condition" :level="0"></sw-condition-line-item-product-states>
 */
Component.extend('sw-condition-line-item-product-states', 'sw-condition-base-line-item', {
    template,

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('string');
        },

        productStates() {
            return [
                {
                    value: 'is-physical',
                    label: this.$tc(
                        'global.sw-condition-generic.cartLineItemProductStates.productState.options.is-physical',
                    ),
                },
                {
                    value: 'is-download',
                    label: this.$tc(
                        'global.sw-condition-generic.cartLineItemProductStates.productState.options.is-download',
                    ),
                },
            ];
        },

        productState: {
            get() {
                this.ensureValueExist();
                return this.condition.value.productState;
            },
            set(productState) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, productState };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.productState']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueProductStateError;
        },
    },
});
