import template from './sw-sales-channel-detail-account-connect.html.twig';
import './sw-sales-channel-detail-account-connect.scss';

const { Component } = Shopware;

Component.register('sw-sales-channel-detail-account-connect', {
    template,

    props: {
        isGoogleShoppingCreate: {
            required: true
        }
    },

    methods: {
        onConnectToGoogle() {
            this.$emit('on-connect-to-google');
        }
    }
});
