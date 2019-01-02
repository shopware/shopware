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
Component.extend('sw-condition-shipping-country', 'sw-condition-placeholder', {
    template,

    computed: {
        operators() {
            return this.ruleConditionService.operatorSets.multiStore;
        }
    },

    methods: {
        getCountryStore() {
            return State.getStore('country');
        }
    }
});
