import template from './sw-condition-billing-street.html.twig';

const { Component } = Shopware;

/**
 * @public
 * @description Condition for the BillingStreetRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-billing-street :condition="condition" :level="0"></sw-condition-billing-street>
 */
Component.extend('sw-condition-billing-street', 'sw-condition-base', {
    template,
    inject: ['ruleConditionDataProviderService'],

    computed: {
        fieldNames() {
            return ['operator', 'streetName'];
        },
        defaultValues() {
            return {
                operator: this.ruleConditionDataProviderService.operators.equals.identifier
            };
        }
    }
});
