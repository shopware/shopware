import { Component } from 'src/core/shopware';
import LocalStore from 'src/core/data/LocalStore';
import template from './sw-condition-goods-price.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-goods-price :condition="condition"></sw-condition-goods-price>
 */
Component.extend('sw-condition-goods-price', 'sw-condition-base', {
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
            return ['operator', 'amount'];
        },
        defaultValues() {
            return {
                operator: this.ruleConditionDataProviderService.operators.equals.identifier
            };
        }
    }
});
