import template from './sw-condition-different-addresses.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @deprecated tag:v6.5.0 This rule component will be removed. Use sw-condition-generic instead.
 * @public
 * @package business-ops
 * @description Condition for the DifferentAddressesRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-different-addresses :condition="condition" :level="0"></sw-condition-different-addresses>
 */
Component.extend('sw-condition-different-addresses', 'sw-condition-base', {
    template,

    computed: {
        selectValues() {
            return [
                {
                    label: this.$tc('global.sw-condition.condition.yes'),
                    value: true,
                },
                {
                    label: this.$tc('global.sw-condition.condition.no'),
                    value: false,
                },
            ];
        },
        isDifferent: {
            get() {
                this.ensureValueExist();
                return this.condition.value.isDifferent;
            },
            set(isDifferent) {
                this.condition.value = { ...this.condition.value, isDifferent };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.isDifferent']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueIsDifferentError;
        },
    },
});
