import { Component } from 'src/core/shopware';
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
            return [
                { label: 'global.sw-condition.condition.yes', value: true },
                { label: 'global.sw-condition.condition.no', value: false }
            ];
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
