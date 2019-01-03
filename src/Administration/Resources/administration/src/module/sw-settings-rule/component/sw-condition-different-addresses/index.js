import { Component } from 'src/core/shopware';
import template from './sw-condition-different-addresses.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-different-addresses :condition="condition"></sw-condition-different-address>
 */
Component.extend('sw-condition-different-addresses', 'sw-condition-base', {
    template,

    computed: {
        operator() {
            return this.ruleConditionService.operators.equals;
        },
        fieldNames() {
            return ['isDifferent'];
        }
    }
});
