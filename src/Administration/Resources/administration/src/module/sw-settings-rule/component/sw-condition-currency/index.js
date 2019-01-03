import { Component, State } from 'src/core/shopware';
import template from './sw-condition-currency.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-currency :condition="condition"></sw-condition-currency>
 */
Component.extend('sw-condition-currency', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.ruleConditionService.operatorSets.multiStore;
        },
        fieldNames() {
            return ['operator', 'currencyIds'];
        }
    },

    methods: {
        getCurrencyStore() {
            return State.getStore('currency');
        }
    }
});
