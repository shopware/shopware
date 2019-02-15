import { Component } from 'src/core/shopware';
import LocalStore from 'src/core/data/LocalStore';
import template from './sw-condition-is-new-customer.html.twig';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-is-new-customer :condition="condition"></sw-condition-is-new-customer>
 */
Component.extend('sw-condition-is-new-customer', 'sw-condition-base', {
    template,

    computed: {
        selectValues() {
            const values = [
                {
                    label: this.$tc('global.sw-condition.condition.yes'),
                    value: 'true',
                    meta: {
                        viewData: {
                            label: this.$tc('global.sw-condition.condition.yes'),
                            value: this.$tc('global.sw-condition.condition.yes')
                        }
                    }
                },
                {
                    label: this.$tc('global.sw-condition.condition.no'),
                    value: 'false',
                    meta: {
                        viewData: {
                            label: this.$tc('global.sw-condition.condition.no'),
                            value: this.$tc('global.sw-condition.condition.no')
                        }
                    }
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
            isNew: String(this.condition.value.isNew)
        };
    }
});
