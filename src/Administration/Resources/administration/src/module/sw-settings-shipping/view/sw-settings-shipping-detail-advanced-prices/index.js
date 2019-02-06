import { Component, State } from 'src/core/shopware';
import LocalStore from 'src/core/data/LocalStore';
import utils from 'src/core/service/util.service';
import template from './sw-settings-shipping-detail-advanced-prices.html.twig';

Component.register('sw-settings-shipping-detail-advanced-prices', {
    template,
    props: {
        shippingMethod: {
            type: Object,
            required: true,
            default: {}
        }
    },
    computed: {
        selectValues() {
            const values = [
                {
                    label: this.$tc('sw-settings-shipping.constants.weight'),
                    value: '0'
                },
                {
                    label: this.$tc('sw-settings-shipping.constants.price'),
                    value: '1'
                },
                {
                    label: this.$tc('sw-settings-shipping.constants.lineItemCount'),
                    value: '2'
                }
            ];

            return new LocalStore(values, 'value');
        },
        productStore() {
            return State.getStore('product');
        },
        priceStore() {
            return this.shippingMethod.getAssociation('prices');
        }
    },
    methods: {
        createId() {
            return utils.createId();
        },
        defaultValue() {
            return {
                calculation: '0'
            };
        }
    },
    watch: {
        calculation: {
            handler(newValue) {
                if (!newValue) {
                    return;
                }
                this.shippingMethod.calculation = Number(newValue);
            }
        }
    },
    data() {
        return {
            calculation: String(this.shippingMethod.calculation)
        };
    }
});
