import template from './sw-condition-line-item-of-type.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Condition for the LineItemOfTypeRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-line-item-of-type :condition="condition" :level="0"></sw-condition-line-item-of-type>
 */
Component.extend('sw-condition-line-item-of-type', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('string');
        },

        lineItemTypes() {
            return [
                {
                    value: 'product',
                    label: this.$tc('global.sw-condition.condition.lineItemOfTypeRule.product'),
                },
                {
                    value: 'discount_surcharge',
                    label: this.$tc('global.sw-condition.condition.lineItemOfTypeRule.discount_surcharge'),
                },
            ];
        },

        lineItemType: {
            get() {
                this.ensureValueExist();
                return this.condition.value.lineItemType;
            },
            set(lineItemType) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, lineItemType };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.lineItemType']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueLineItemTypeError;
        },
    },
});
