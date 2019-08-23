import template from './sw-condition-shipping-zip-code.html.twig';

const { Component } = Shopware;

/**
 * @public
 * @description Condition for the ShippingZipCodeRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-shipping-zip-code :condition="condition" :level="0"></sw-condition-shipping-zip-code>
 */
Component.extend('sw-condition-shipping-zip-code', 'sw-condition-base', {
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
