import template from './sw-condition-line-item-tag.html.twig';

const { Component, StateDeprecated } = Shopware;

/**
 * @public
 * @description Condition for the LineItemTagRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-line-item-tag :condition="condition" :level="0"></sw-condition-line-item-tag>
 */
Component.extend('sw-condition-line-item-tag', 'sw-condition-base', {
    template,
    inject: ['ruleConditionDataProviderService'],

    computed: {
        fieldNames() {
            return ['operator', 'identifiers'];
        },
        defaultValues() {
            return {
                operator: this.ruleConditionDataProviderService.operators.isOneOf.identifier
            };
        }
    },

    methods: {
        getTagStore() {
            return StateDeprecated.getStore('tag');
        }
    }
});
