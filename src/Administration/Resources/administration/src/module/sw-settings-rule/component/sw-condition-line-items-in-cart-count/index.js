import template from './sw-condition-line-items-in-cart-count.html.twig';

const { Component } = Shopware;

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
