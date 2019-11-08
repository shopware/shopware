import LocalStore from 'src/core/data/LocalStore';
import template from './sw-condition-different-addresses.html.twig';

const { Component } = Shopware;

/**
 * @public
 * @description Condition for the DifferentAddressesRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-different-addresses :condition="condition" :level="0"></sw-condition-different-addresses>
 */
Component.extend('sw-condition-different-addresses', 'sw-condition-base', {
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
                this.condition.value.isDifferent = newValue === String(true);
            }
        }
    },

    data() {
        return {
            isDifferent: this.condition.value.isDifferent ? String(this.condition.value.isDifferent) : String(true)
        };
    }
});
