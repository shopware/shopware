import template from './sw-condition-goods-count.html.twig';
import './sw-condition-goods-count.scss';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Condition for the GoodsCountRuleNonDistinct. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-goods-count-non-distinct :condition="condition" :level="0"></sw-condition-goods-count-non-distinct>
 */
Component.extend('sw-condition-goods-count-non-distinct', 'sw-condition-base', {
    template,

    data() {
        return {
            showFilterModal: false
        };
    },

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('number');
        },

        count: {
            get() {
                this.ensureValueExist();
                return this.condition.value.count;
            },
            set(count) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, count };
            }
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.count']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueCountError;
        }
    }
});
