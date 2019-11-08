import template from './sw-condition-billing-zip-code.html.twig';

const { Component } = Shopware;

/**
 * @public
 * @description Condition for the BillingZipCodeRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-billing-zip-code :condition="condition" :level="0"></sw-condition-billing-zip-code>
 */
Component.extend('sw-condition-billing-zip-code', 'sw-condition-base', {
    template,
    inject: ['ruleConditionDataProviderService'],

    computed: {
        fieldNames() {
            return ['operator', 'zipCodes'];
        },
        defaultValues() {
            return {
                operator: this.ruleConditionDataProviderService.operators.isOneOf.identifier
            };
        }
    }
});
