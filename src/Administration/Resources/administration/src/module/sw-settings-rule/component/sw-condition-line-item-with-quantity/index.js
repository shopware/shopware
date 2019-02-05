import { Component, State } from 'src/core/shopware';
import LocalStore from 'src/core/data/LocalStore';
import template from './sw-condition-line-item-with-quantity.html.twig';
import './sw-condition-line-item-with-quantity.scss';

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
    inject: ['ruleConditionDataProviderService'],

    computed: {
        operators() {
            const operators = {};
            Object.values(this.ruleConditionDataProviderService.operatorSets.number).forEach(operator => {
                operators[operator.identifier] = operator;
                operators[operator.identifier].meta = {
                    viewData: { label: this.$tc(operator.label), identifier: this.$tc(operator.label) }
                };
            });

            return new LocalStore(operators, 'identifier');
        },
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
