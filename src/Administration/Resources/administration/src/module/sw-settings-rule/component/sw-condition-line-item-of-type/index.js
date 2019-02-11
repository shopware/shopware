import { Component } from 'src/core/shopware';
import template from './sw-condition-line-item-of-type.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-line-item-of-type :condition="condition"></sw-condition-line-item-of-type>
 */
Component.extend('sw-condition-line-item-of-type', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.conditionStore.operatorSets.string;
        },
        lineItemTypes() {
            return {
                // TODO: Add line item types
                product: {
                    label: 'global.sw-condition.condition.lineItemOfTypeRule.product'
                }
            };
        },
        fieldNames() {
            return ['operator', 'lineItemType'];
        },
        defaultValues() {
            return {
                operator: this.conditionStore.operators.equals.identifier
            };
        }
    }
});
