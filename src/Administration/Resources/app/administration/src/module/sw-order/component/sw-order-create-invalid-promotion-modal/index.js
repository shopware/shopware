import template from './sw-order-create-invalid-promotion-modal.html.twig';
import './sw-order-create-invalid-promotion-modal.scss';

const { Component, State } = Shopware;

Component.register('sw-order-create-invalid-promotion-modal', {
    template,

    computed: {
        invalidPromotionCodes() {
            return State.getters['swOrder/invalidPromotionCodes'];
        },
    },

    methods: {
        onClose() {
            this.$emit('close');
        },

        onConfirm() {
            this.$emit('confirm');
        },
    },
});
