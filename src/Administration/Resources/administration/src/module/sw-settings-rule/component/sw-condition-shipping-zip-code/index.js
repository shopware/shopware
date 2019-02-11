import { Component } from 'src/core/shopware';
import template from './sw-condition-shipping-zip-code.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-shipping-zip-code :condition="condition"></sw-condition-shipping-zip-code>
 */
Component.extend('sw-condition-shipping-zip-code', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.conditionStore.operatorSets.multiStore;
        },
        fieldNames() {
            return ['operator', 'zipCodes'];
        },
        defaultValues() {
            return {
                operator: this.conditionStore.operators.isOneOf.identifier
            };
        }
    }
});
