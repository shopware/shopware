import template from './sw-condition-goods-count.html.twig';
import './sw-condition-goods-count.scss';

const { Component } = Shopware;

/**
 * @public
 * @description Condition for the GoodsCountRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-goods-count :condition="condition" :level="0"></sw-condition-goods-count>
 */
Component.extend('sw-condition-goods-count', 'sw-condition-base', {
    template,
    inject: ['ruleConditionDataProviderService'],

    computed: {
        fieldNames() {
            return ['operator', 'count'];
        },
        defaultValues() {
            return {
                operator: this.ruleConditionDataProviderService.operators.equals.identifier
            };
        },
        conditionClass() {
            return 'sw-condition-goods-count';
        }
    },

    // TODO: extract data, methods and template (see sw-condition-goods-price)
    // Wait for extending an extended component
    data() {
        return {
            showFilterModal: false
        };
    },

    methods: {
        deleteCondition() {
            this.deleteChildren(this.condition.children);

            this.$super('deleteCondition');
        },
        deleteChildren(children) {
            children.forEach((child) => {
                child.delete();
            });
        }
    }
});
