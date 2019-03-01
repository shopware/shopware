import { Component } from 'src/core/shopware';
import template from './sw-condition-goods-price.html.twig';

/**
 * @public
 * @description Condition for the GoodsPriceRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-goods-price :condition="condition" :level="0"></sw-condition-goods-price>
 */
Component.extend('sw-condition-goods-price', 'sw-condition-base', {
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
