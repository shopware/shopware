import template from './sw-condition-line-items-in-cart.html.twig';

const { Component, StateDeprecated } = Shopware;

/**
 * @public
 * @description Condition for the LineItemsInCartRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-line-items-in-cart :condition="condition" :level="0"></sw-condition-line-items-in-cart>
 */
Component.extend('sw-condition-line-items-in-cart', 'sw-condition-base', {
    template,
    inject: ['ruleConditionDataProviderService'],

    computed: {
        fieldNames() {
            return ['operator', 'identifiers'];
        },
        defaultValues() {
            return {
                operator: this.ruleConditionDataProviderService.operators.isOneOf.identifier
            };
        }
    },

    methods: {
        getProductStore() {
            return StateDeprecated.getStore('product');
        }
    }
});
