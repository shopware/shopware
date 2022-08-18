import template from './sw-order-leave-page-modal.html.twig';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-order-leave-page-modal', {
    template,
    methods: {
        onConfirm() {
            this.$emit('page-leave-confirm');
        },
        onCancel() {
            this.$emit('page-leave-cancel');
        },
    },
});
