import { Component } from 'src/core/shopware';
import LocalStore from 'src/core/data/LocalStore';
import template from './sw-condition-line-item-of-type.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-line-item-of-type :condition="condition"></sw-condition-line-item-of-type>
 */
Component.extend('sw-condition-line-item-of-type', 'sw-condition-base', {
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
        lineItemTypes() {
            return {
                // TODO: Add line item types
                product: {
                    label: 'global.sw-condition.condition.lineItemOfTypeRule.product'
                }
            };
        },
        fieldNames() {
            return ['operator', 'lineItemType'];
        },
        defaultValues() {
            return {
                operator: this.ruleConditionDataProviderService.operators.equals.identifier
            };
        }
    }
});
