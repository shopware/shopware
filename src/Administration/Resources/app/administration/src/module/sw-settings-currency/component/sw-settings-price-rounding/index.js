import template from './sw-settings-price-rounding.html.twig';
import './sw-settings-price-rounding.scss';

const { Component } = Shopware;

Component.register('sw-settings-price-rounding', {
    template,

    props: {
        itemRounding: {
            type: Object,
            required: false,
            default() {
                return {};
            }
        },

        totalRounding: {
            type: Object,
            required: false,
            default() {
                return {};
            }
        }
    },

    data() {
        return {
            intervalOptions: [
                { label: '0.01', value: 0.01 },
                { label: '0.05', value: 0.05 },
                { label: '0.10', value: 0.10 },
                { label: '0.50', value: 0.50 },
                { label: '1.00', value: 1 }
            ]
        };
    }
});
