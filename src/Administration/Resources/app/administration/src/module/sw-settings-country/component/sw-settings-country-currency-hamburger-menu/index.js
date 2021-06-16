import template from './sw-settings-country-currency-hamburger-menu.html.twig';
import './sw-settings-country-currency-hamburger-menu.scss';

const { Component } = Shopware;

Component.register('sw-settings-country-currency-hamburger-menu', {
    template,
    flag: 'FEATURE_NEXT_14114',

    inject: [
        'acl',
        'feature',
    ],

    props: {
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
        options: {
            type: Array,
            required: true,
        },
    },

    methods: {
        onCheckCurrency(currencyId, isChecked) {
            this.$emit('currency-change', currencyId, isChecked);
        },
    },
});
