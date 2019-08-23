import template from './sw-condition-line-item-total-price.html.twig';

const { Component } = Shopware;

/**
 * @public
 * @description Condition for the LineItemTotalPriceRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-line-item-total-price :condition="condition" :level="0"></sw-condition-line-item-total-price>
 */
Component.extend('sw-condition-line-item-total-price', 'sw-condition-base', {
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
        }
    }
});
