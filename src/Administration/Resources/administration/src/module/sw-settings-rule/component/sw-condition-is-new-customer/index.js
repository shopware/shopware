import { Component } from 'src/core/shopware';
import template from './sw-condition-is-new-customer.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-is-new-customer :condition="condition"></sw-condition-is-new-customer>
 */
Component.extend('sw-condition-is-new-customer', 'sw-condition-placeholder', {
    template,

    computed: {
        operator() {
            return this.ruleConditionService.operators.equals;
        },
        fieldNames() {
            return ['isNew'];
        }
    }
});
