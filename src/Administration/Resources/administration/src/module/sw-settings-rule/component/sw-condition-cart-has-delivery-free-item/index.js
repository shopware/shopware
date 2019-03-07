import { Component } from 'src/core/shopware';
import LocalStore from 'src/core/data/LocalStore';
import template from './sw-condition-cart-has-delivery-free-item.twig';

Component.extend('sw-condition-cart-has-delivery-free-item', 'sw-condition-base', {
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
            return ['allowed'];
        }
    },

    data() {
        return {
            allowed: this.condition.value.allowed !== undefined ? String(this.condition.value.allowed) : 'true'
        };
    },

    watch: {
        allowed: {
            handler(newValue) {
                this.condition.value.allowed = newValue === 'true';
            }
        }
    }
});
