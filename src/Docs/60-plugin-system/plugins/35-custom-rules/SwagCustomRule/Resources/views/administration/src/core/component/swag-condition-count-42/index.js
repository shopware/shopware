import { Component } from 'src/core/shopware';
import template from './swag-condition-count-42.html.twig';

Component.extend('swag-condition-count-42', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            const operators = this.ruleConditionDataProviderService.operators;
            return [operators.equals, operators.notEquals];
        },
        fieldNames() {
            return ['operator', 'count'];
        },
        defaultValues() {
            return {
                operator: this.ruleConditionDataProviderService.operators.equals.identifier
            };
        }
    }
});
