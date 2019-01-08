import { Component, State } from 'src/core/shopware';
import template from './sw-condition-customer-group.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-customer-group :condition="condition"></sw-condition-and-container>
 */
Component.extend('sw-condition-customer-group', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.ruleConditionService.operatorSets.multiStore;
        },
        fieldNames() {
            return ['operator', 'customerGroupIds'];
        },
        defaultValues() {
            return {
                operator: this.ruleConditionService.operators.isOneOf.identifier
            };
        }
    },

    methods: {
        getCustomerGroupStore() {
            return State.getStore('customer_group');
        }
    }
});
