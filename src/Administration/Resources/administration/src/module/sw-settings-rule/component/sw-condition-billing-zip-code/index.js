import { Component } from 'src/core/shopware';
import template from './sw-condition-billing-zip-code.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-billing-zip-code :condition="condition"></sw-condition-billing-zip-code>
 */
Component.extend('sw-condition-billing-zip-code', 'sw-condition-base', {
    template,
    inject: ['ruleConditionDataProviderService'],

    computed: {
        fieldNames() {
            return ['operator', 'zipCodes'];
        },
        defaultValues() {
            return {
                operator: this.ruleConditionDataProviderService.operators.isOneOf.identifier
            };
        }
    }
});
