import template from './sw-settings-country-currency-hamburger-menu.html.twig';
import './sw-settings-country-currency-hamburger-menu.scss';

const { Component } = Shopware;

Component.register('sw-settings-country-currency-hamburger-menu', {
    template,

    inject: ['acl'],

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
