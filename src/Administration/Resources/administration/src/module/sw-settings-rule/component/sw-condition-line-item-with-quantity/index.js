import { Component, State } from 'src/core/shopware';
import template from './sw-condition-line-item-with-quantity.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-line-item-with-quantity :condition="condition"></sw-condition-line-item-with-quantity>
 */
Component.extend('sw-condition-line-item-with-quantity', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.ruleConditionService.operatorSets.number;
        },
        fieldNames() {
            return ['id', 'operator', 'quantity'];
        }
    },

    methods: {
        getProductStore() {
            return State.getStore('product');
        }
    }
});
