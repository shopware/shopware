import LocalStore from 'src/core/data/LocalStore';
import template from './sw-condition-line-item-of-type.html.twig';

const { Component } = Shopware;

/**
 * @public
 * @description Condition for the LineItemOfTypeRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-line-item-of-type :condition="condition" :level="0"></sw-condition-line-item-of-type>
 */
Component.extend('sw-condition-line-item-of-type', 'sw-condition-base', {
    template,
    inject: ['ruleConditionDataProviderService'],

    computed: {
        lineItemTypes() {
            return [
                {
                    value: 'product',
                    label: this.$tc('global.sw-condition.condition.lineItemOfTypeRule.product')
                },
                {
                    value: 'discount_surcharge',
                    label: this.$tc('global.sw-condition.condition.lineItemOfTypeRule.discount_surcharge')
                }
            ];
        },
        lineItemTypeStore() {
            return new LocalStore(this.lineItemTypes, 'value');
        },
        fieldNames() {
            return ['operator', 'lineItemType'];
        },
        defaultValues() {
            return {
                operator: this.ruleConditionDataProviderService.operators.equals.identifier
            };
        }
    }
});
