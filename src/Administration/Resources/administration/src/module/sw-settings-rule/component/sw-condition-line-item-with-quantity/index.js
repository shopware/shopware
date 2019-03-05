import { Component, State } from 'src/core/shopware';
import template from './sw-condition-line-item-with-quantity.html.twig';
import './sw-condition-line-item-with-quantity.scss';

/**
 * @public
 * @description Condition for the LineItemWithQuantityRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-line-item-with-quantity :condition="condition" :level="0"></sw-condition-line-item-with-quantity>
 */
Component.extend('sw-condition-line-item-with-quantity', 'sw-condition-base', {
    template,
    inject: ['ruleConditionDataProviderService'],

    computed: {
        fieldNames() {
            return ['id', 'operator', 'quantity'];
        },
        conditionClass() {
            return 'sw-condition-line-item-with-quantity';
        },
        defaultValues() {
            return {
                operator: this.ruleConditionDataProviderService.operators.equals.identifier
            };
        }
    },

    methods: {
        getProductStore() {
            return State.getStore('product');
        }
    }
});
