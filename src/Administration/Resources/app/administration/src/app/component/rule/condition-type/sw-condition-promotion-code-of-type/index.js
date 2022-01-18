import template from './sw-condition-promotion-code-of-type.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Condition for the PromotionOfTypeRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-promotion-code-of-type :condition="condition" :level="0"></sw-condition-promotion-code-of-type>
 */
Component.extend('sw-condition-promotion-code-of-type', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('string');
        },

        promotionCodeTypes() {
            return [
                {
                    value: 'global',
                    label: this.$tc('global.sw-condition.condition.promotionCodeOfTypeRule.global'),
                },
                {
                    value: 'fixed',
                    label: this.$tc('global.sw-condition.condition.promotionCodeOfTypeRule.fixed'),
                },
                {
                    value: 'individual',
                    label: this.$tc('global.sw-condition.condition.promotionCodeOfTypeRule.individual'),
                },
            ];
        },

        promotionCodeType: {
            get() {
                this.ensureValueExist();
                return this.condition.value.promotionCodeType;
            },
            set(promotionCodeType) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, promotionCodeType };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.promotionCodeType']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValuePromotionCodeTypeError;
        },
    },
});
