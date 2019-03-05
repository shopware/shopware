import { Component, State } from 'src/core/shopware';
import template from './sw-condition-shipping-country.html.twig';

/**
 * @public
 * @description Condition for the ShippingCountryRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-shipping-country :condition="condition" :level="0"></sw-condition-shipping-country>
 */
Component.extend('sw-condition-shipping-country', 'sw-condition-base', {
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
            return State.getStore('country');
        }
    }
});
