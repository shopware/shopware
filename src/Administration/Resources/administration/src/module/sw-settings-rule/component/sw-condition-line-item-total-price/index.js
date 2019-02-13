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
    inject: ['ruleConditionDataProviderService'],

    computed: {
        fieldNames() {
            return ['operator', 'amount'];
        },
        defaultValues() {
            return {
                operator: this.ruleConditionDataProviderService.operators.equals.identifier
            };
        }
    }
});
