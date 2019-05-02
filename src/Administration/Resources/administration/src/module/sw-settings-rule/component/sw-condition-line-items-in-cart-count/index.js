import { Component } from 'src/core/shopware';
import template from './sw-condition-line-items-in-cart-count.html.twig';

Component.extend('sw-condition-line-items-in-cart-count', 'sw-condition-base', {
    template,
    inject: ['ruleConditionDataProviderService'],

    computed: {
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
