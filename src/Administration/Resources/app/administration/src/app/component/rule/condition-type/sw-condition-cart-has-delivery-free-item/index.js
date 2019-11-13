import LocalStore from 'src/core/data/LocalStore';
import template from './sw-condition-cart-has-delivery-free-item.twig';

const { Component } = Shopware;

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
        },
        defaultValues() {
            return {
                allowed: true
            };
        }
    },

    data() {
        return {
            allowed: this.condition.value.allowed ? String(this.condition.value.allowed) : 'true'
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
