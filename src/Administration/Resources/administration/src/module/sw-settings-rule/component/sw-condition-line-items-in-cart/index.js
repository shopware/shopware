import { Component, State } from 'src/core/shopware';
import template from './sw-condition-line-items-in-cart.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-line-items-in-cart :condition="condition"></sw-condition-line-items-in-cart>
 */
Component.extend('sw-condition-line-items-in-cart', 'sw-condition-placeholder', {
    template,

    computed: {
        operators() {
            return this.ruleConditionService.operatorSets.multiStore;
        }
    },

    methods: {
        getProductStore() {
            return State.getStore('product');
        }
    }
});
