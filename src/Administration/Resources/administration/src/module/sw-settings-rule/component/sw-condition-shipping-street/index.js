import { Component } from 'src/core/shopware';
import LocalStore from 'src/core/data/LocalStore';
import template from './sw-condition-shipping-street.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-shipping-street :condition="condition"></sw-condition-shipping-street>
 */
Component.extend('sw-condition-shipping-street', 'sw-condition-base', {
    template,
    inject: ['ruleConditionDataProviderService'],

    computed: {
        operators() {
            const operators = {};
            Object.values(this.ruleConditionDataProviderService.operatorSets.string).forEach(operator => {
                operators[operator.identifier] = operator;
                operators[operator.identifier].meta = {
                    viewData: { label: this.$tc(operator.label), identifier: this.$tc(operator.label) }
                };
            });

            return new LocalStore(operators, 'identifier');
        },
        fieldNames() {
            return ['operator', 'streetName'];
        },
        defaultValues() {
            return {
                operator: this.ruleConditionDataProviderService.operators.equals.identifier
            };
        }
    }
});
