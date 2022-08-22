import template from './sw-flow-leave-page-modal.html.twig';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-flow-leave-page-modal', {
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
