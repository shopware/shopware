import { Component, State } from 'src/core/shopware';
import template from './sw-condition-line-item.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-line-item :condition="condition"></sw-condition-line-item>
 */
Component.extend('sw-condition-line-item', 'sw-condition-placeholder', {
    template,

    computed: {
        operators() {
            return this.ruleConditionService.operatorSets.multiStore;
        },
        fieldNames() {
            return ['operator', 'identifiers'];
        }
    },

    methods: {
        getProductStore() {
            return State.getStore('product');
        }
    }
});
