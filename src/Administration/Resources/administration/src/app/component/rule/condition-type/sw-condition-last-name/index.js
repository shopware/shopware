import template from './sw-condition-last-name.html.twig';

const { Component } = Shopware;

/**
 * @public
 * @description Condition for the LastNameRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-last-name :condition="condition" :level="0"></sw-condition-last-name>
 */
Component.extend('sw-condition-last-name', 'sw-condition-base', {
    template,
    inject: ['ruleConditionDataProviderService'],

    computed: {
        fieldNames() {
            return ['operator', 'lastName'];
        },
        defaultValues() {
            return {
                operator: this.ruleConditionDataProviderService.operators.equals.identifier
            };
        }
    }
});
