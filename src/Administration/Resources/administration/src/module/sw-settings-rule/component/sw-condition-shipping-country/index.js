import { Component, State } from 'src/core/shopware';
import template from './sw-condition-shipping-country.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-shipping-country :condition="condition"></sw-condition-shipping-country>
 */
Component.extend('sw-condition-shipping-country', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.conditionStore.operatorSets.multiStore;
        },
        fieldNames() {
            return ['operator', 'countryIds'];
        },
        defaultValues() {
            return {
                operator: this.conditionStore.operators.isOneOf.identifier
            };
        }
    },

    methods: {
        getCountryStore() {
            return State.getStore('country');
        }
    }
});
