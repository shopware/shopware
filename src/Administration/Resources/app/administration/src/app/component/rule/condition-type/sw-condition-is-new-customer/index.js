import LocalStore from 'src/core/data/LocalStore';
import template from './sw-condition-is-new-customer.html.twig';

const { Component } = Shopware;

/**
 * @public
 * @description Condition for the IsNewCustomerRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-is-new-customer :condition="condition" :level="0"></sw-condition-is-new-customer>
 */
Component.extend('sw-condition-is-new-customer', 'sw-condition-base', {
    template,

    computed: {
        selectValues() {
            const values = [
                {
                    label: this.$tc('global.sw-condition.condition.yes'),
                    value: 'true'
                },
                {
                    label: this.$tc('global.sw-condition.condition.no'),
                    value: 'false'
                }
            ];

            return new LocalStore(values, 'value');
        },
        fieldNames() {
            return ['isNew'];
        },
        defaultValues() {
            return {
                isNew: true
            };
        }
    },

    watch: {
        isNew: {
            handler(newValue) {
                this.condition.value.isNew = newValue === 'true';
            }
        }
    },

    data() {
        return {
            isNew: this.condition.value.isNew ? String(this.condition.value.isNew) : String(true)
        };
    }
});
