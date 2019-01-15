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
Component.extend('sw-condition-goods-count', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.conditionStore.operatorSets.number;
        },
        fieldNames() {
            return ['operator', 'count'];
        },
        defaultValues() {
            return {
                operator: this.conditionStore.operators.equals.identifier
            };
        }
    }
});
