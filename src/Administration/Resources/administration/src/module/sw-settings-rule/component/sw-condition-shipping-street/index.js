import { Component } from 'src/core/shopware';
import template from './sw-condition-shipping-street.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-shipping-street :condition="condition"></sw-condition-shipping-street>
 */
Component.extend('sw-condition-shipping-street', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.ruleConditionService.operatorSets.string;
        },
        defaultValues() {
            return {
                operator: this.ruleConditionService.operators.equals.identifier
            };
        }
    }
});
