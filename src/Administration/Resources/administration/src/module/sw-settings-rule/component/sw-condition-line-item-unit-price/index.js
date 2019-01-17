import { Component } from 'src/core/shopware';
import template from './sw-condition-line-item-unit-price.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-line-item-unit-price :condition="condition"></sw-condition-line-item-unit-price>
 */
Component.extend('sw-condition-line-item-unit-price', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.ruleConditionDataProviderService.operatorSets.number;
        },
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
