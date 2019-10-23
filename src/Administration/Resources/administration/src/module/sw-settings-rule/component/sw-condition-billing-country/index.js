import template from './sw-condition-billing-country.html.twig';

const { Component, StateDeprecated } = Shopware;

/**
 * @public
 * @description Condition for the BillingCountryRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-billing-country :condition="condition" :level="0"></sw-condition-billing-country>
 */
Component.extend('sw-condition-billing-country', 'sw-condition-base', {
    template,
    inject: ['ruleConditionDataProviderService'],

    computed: {
        fieldNames() {
            return ['operator', 'countryIds'];
        },
        defaultValues() {
            return {
                operator: this.ruleConditionDataProviderService.operators.isOneOf.identifier
            };
        }
    },

    methods: {
        getCountryStore() {
            return StateDeprecated.getStore('country');
        }
    }
});
