import { Component } from 'src/core/shopware';
import template from './sw-condition-goods-count.html.twig';

/**
 * @public
 * @description Condition for the GoodsCountRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-goods-count :condition="condition" :level="0"></sw-condition-goods-count>
 */
Component.extend('sw-condition-goods-count', 'sw-condition-base', {
    template,
    inject: ['ruleConditionDataProviderService'],

    computed: {
        fieldNames() {
            return ['operator', 'count'];
        },
        defaultValues() {
            return {
                operator: this.ruleConditionDataProviderService.operators.equals.identifier
            };
        }
    }
});
