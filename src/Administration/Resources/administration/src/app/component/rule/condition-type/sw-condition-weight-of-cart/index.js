import template from './sw-condition-weight-of-cart.html.twig';

const { Component } = Shopware;

/**
 * @public
 * @description Condition for the CartWeightRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-weight-of-cart :condition="condition" :level="0"></sw-condition-weight-of-cart>
 */
Component.extend('sw-condition-weight-of-cart', 'sw-condition-base', {
    template,
    inject: ['ruleConditionDataProviderService'],

    computed: {
        fieldNames() {
            return ['operator', 'weight'];
        },
        defaultValues() {
            return {
                operator: this.ruleConditionDataProviderService.operators.equals.identifier,
                weight: 0.0
            };
        }
    }
});
