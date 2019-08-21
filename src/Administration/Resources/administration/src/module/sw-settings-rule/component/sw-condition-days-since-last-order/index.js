import template from './sw-condition-days-since-last-order.html.twig';

const { Component } = Shopware;

Component.extend('sw-condition-days-since-last-order', 'sw-condition-base', {
    template,
    inject: ['ruleConditionDataProviderService'],

    computed: {
        fieldNames() {
            return ['operator', 'daysPassed'];
        },
        defaultValues() {
            return {
                operator: this.ruleConditionDataProviderService.operators.equals.identifier
            };
        }
    }
});
