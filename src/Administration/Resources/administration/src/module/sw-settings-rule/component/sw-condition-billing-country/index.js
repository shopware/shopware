import { Component, State } from 'src/core/shopware';
import template from './sw-condition-billing-country.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-billing-country :condition="condition"></sw-condition-billing-country>
 */
Component.extend('sw-condition-billing-country', 'sw-condition-base', {
    template,
    inject: ['ruleConditionDataProviderService'],

    computed: {
        fieldNames() {
            return ['operator', 'countryIds'];
        },
        conditionClass() {
            return 'sw-condition-billing-country';
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
