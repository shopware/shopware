import { Component } from 'src/core/shopware';
import template from './sw-condition-billing-street.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-billing-street :condition="condition"></sw-condition-billing-street>
 */
Component.extend('sw-condition-billing-street', 'sw-condition-placeholder', {
    template,

    computed: {
        operators() {
            return this.ruleConditionService.operatorSets.string;
        },
        fieldNames() {
            return ['operator', 'streetName'];
        }
    }
});
