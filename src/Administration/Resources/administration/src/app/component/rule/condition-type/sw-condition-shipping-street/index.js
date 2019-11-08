import template from './sw-condition-shipping-street.html.twig';

const { Component } = Shopware;

/**
 * @public
 * @description Condition for the ShippingStreetRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-shipping-street :condition="condition" :level="0"></sw-condition-shipping-street>
 */
Component.extend('sw-condition-shipping-street', 'sw-condition-base', {
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
