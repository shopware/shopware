import { Component } from 'src/core/shopware';
import template from './sw-condition-cart-amount.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-cart-amount :condition="condition"></sw-condition-cart-amount>
 */
Component.extend('sw-condition-cart-amount', 'sw-condition-placeholder', {
    template,

    computed: {
        operators() {
            return this.ruleConditionService.operatorSets.number;
        },
        fieldNames() {
            return ['operator', 'amount'];
        }
    }
});
