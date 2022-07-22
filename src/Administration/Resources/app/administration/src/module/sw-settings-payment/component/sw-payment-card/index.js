import template from './sw-payment-card.html.twig';
import './sw-payment-card.scss';

const { Component } = Shopware;

Component.register('sw-payment-card', {
    template,

    inject: ['acl'],

    props: {
        paymentMethod: {
            type: Object,
            required: true,
        },
    },

    computed: {
        previewUrl() {
            return this.paymentMethod.media ? this.paymentMethod.media.url : null;
        },
    },

    methods: {
        async setPaymentMethodActive(active) {
            this.paymentMethod.active = active;

            this.$emit('set-payment-active', this.paymentMethod);
        },
    },
});
