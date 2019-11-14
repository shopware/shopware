import template from './sw-condition-billing-customer-number.html.twig';

const { Component } = Shopware;

/**
 * @public
 * @description Condition for the CustomerNumberRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-customer-number :condition="condition" :level="0"></sw-condition-customer-number>
 */
Component.extend('sw-condition-customer-number', 'sw-condition-base', {
    template,
    inject: ['ruleConditionDataProviderService'],

    computed: {
        fieldNames() {
            return ['operator', 'numbers'];
        },
        defaultValues() {
            return {
                operator: this.ruleConditionDataProviderService.operators.isOneOf.identifier
            };
        }
    }
});
