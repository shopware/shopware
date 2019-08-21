import template from './sw-category-leave-page-modal.html.twig';

const { Component } = Shopware;

Component.register('sw-category-leave-page-modal', {
    template,

    methods: {
        onConfirm() {
            this.$emit('page-leave-confirm');
        },
        onCancel() {
            this.$emit('page-leave-cancel');
        }
    }
});
