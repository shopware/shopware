import { Component } from 'src/core/shopware';
import template from './sw-condition-last-name.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-last-name :condition="condition"></sw-condition-last-name>
 */
Component.extend('sw-condition-last-name', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.conditionStore.operatorSets.string;
        },
        fieldNames() {
            return ['operator', 'lastName'];
        },
        defaultValues() {
            return {
                operator: this.conditionStore.operators.equals.identifier
            };
        }
    }
});
