import { Component } from 'src/core/shopware';
import template from './sw-condition-goods-count.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-goods-count :condition="condition"></sw-condition-goods-count>
 */
Component.extend('sw-condition-goods-count', 'sw-condition-placeholder', {
    template,

    computed: {
        operators() {
            return this.ruleConditionService.operatorSets.number;
        },
        fieldNames() {
            return ['operator', 'count'];
        }
    }
});
