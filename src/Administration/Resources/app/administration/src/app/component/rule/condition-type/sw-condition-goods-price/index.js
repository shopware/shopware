import template from './sw-condition-goods-price.html.twig';
import './sw-condition-goods-price.scss';

const { Component } = Shopware;

/**
 * @public
 * @description Condition for the GoodsPriceRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-goods-price :condition="condition" :level="0"></sw-condition-goods-price>
 */
Component.extend('sw-condition-goods-price', 'sw-condition-base', {
    template,
    inject: ['ruleConditionDataProviderService'],

    computed: {
        fieldNames() {
            return ['operator', 'amount'];
        },
        defaultValues() {
            return {
                operator: this.ruleConditionDataProviderService.operators.equals.identifier
            };
        },
        conditionClass() {
            return 'sw-condition-goods-price';
        }
    },

    // TODO: extract data, methods and template (see sw-condition-goods-count)
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
