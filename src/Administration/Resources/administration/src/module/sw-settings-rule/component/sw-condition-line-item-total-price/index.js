import { Component } from 'src/core/shopware';
import template from './sw-condition-line-item-total-price.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-line-item-total-price :condition="condition"></sw-condition-line-item-total-price>
 */
Component.extend('sw-condition-line-item-total-price', 'sw-condition-base', {
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
