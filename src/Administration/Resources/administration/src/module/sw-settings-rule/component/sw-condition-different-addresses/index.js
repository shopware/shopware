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
        selectValues() {
            return [
                { label: 'global.sw-condition.condition.yes', value: true },
                { label: 'global.sw-condition.condition.no', value: false }
            ];
        },
        fieldNames() {
            return ['isDifferent'];
        },
        defaultValues() {
            return {
                isDifferent: true
            };
        }
    },

    watch: {
        isDifferent: {
            handler(newValue) {
                this.condition.value.isDifferent = newValue === 'true';
            }
        }
    },

    data() {
        return {
            isDifferent: String(this.condition.value.isDifferent)
        };
    }
});
