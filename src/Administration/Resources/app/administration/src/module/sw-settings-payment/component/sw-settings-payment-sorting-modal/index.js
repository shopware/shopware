import template from './sw-settings-payment-sorting-modal.html.twig';
import './sw-settings-payment-sorting-modal.scss';

const { Component } = Shopware;

Component.register('sw-settings-payment-sorting-modal', {
    template,

    inject: [
        'acl',
    ],

    props: {
        paymentMethods: {
            type: Array,
            required: true,
        },
    },

    data() {
        return {
        };
    },

    methods: {
        closeModal() {
            this.$emit('modal-close');
        },

        applyChanges() {
            // ToDo: NEXT-20937 - do save process

            this.$emit('modal-close');
        },
    },
});
