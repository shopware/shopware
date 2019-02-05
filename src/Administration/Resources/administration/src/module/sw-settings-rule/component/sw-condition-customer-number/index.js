import { Component } from 'src/core/shopware';
import LocalStore from 'src/core/data/LocalStore';
import template from './sw-condition-billing-customer-number.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-customer-number :condition="condition"></sw-condition-customer-number>
 */
Component.extend('sw-condition-customer-number', 'sw-condition-base', {
    template,
    inject: ['ruleConditionDataProviderService'],

    computed: {
        operators() {
            const operators = {};
            Object.values(this.ruleConditionDataProviderService.operatorSets.multiStore).forEach(operator => {
                operators[operator.identifier] = operator;
                operators[operator.identifier].meta = {
                    viewData: { label: this.$tc(operator.label), identifier: this.$tc(operator.label) }
                };
            });

            return new LocalStore(operators, 'identifier');
        },
        fieldNames() {
            return ['operator', 'numbers'];
        },
        defaultValues() {
            return {
                operator: this.ruleConditionDataProviderService.operators.isOneOf.identifier
            };
        }
    }
});
