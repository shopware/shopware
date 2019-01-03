import { Component } from 'src/core/shopware';
import template from './sw-condition-billing-customer-number.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-customer-number :condition="condition"></sw-condition-customer-number>
 */
Component.extend('sw-condition-customer-number', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.ruleConditionService.operatorSets.multiStore;
        },
        fieldNames() {
            return ['operator', 'customerNumbers'];
        }
    }
});
