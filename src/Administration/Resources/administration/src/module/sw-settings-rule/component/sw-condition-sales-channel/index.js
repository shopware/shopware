import template from './sw-condition-sales-channel.html.twig';

const { Component, StateDeprecated } = Shopware;

/**
 * @public
 * @description Condition for the SalesChannelRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-sales-channel :condition="condition" :level="0"></sw-condition-sales-channel>
 */
Component.extend('sw-condition-sales-channel', 'sw-condition-base', {
    template,
    inject: ['ruleConditionDataProviderService'],

    computed: {
        fieldNames() {
            return ['operator', 'salesChannelIds'];
        },
        defaultValues() {
            return {
                operator: this.ruleConditionDataProviderService.operators.isOneOf.identifier
            };
        }
    },

    methods: {
        getSalesChannelStore() {
            return StateDeprecated.getStore('sales_channel');
        }
    }
});
