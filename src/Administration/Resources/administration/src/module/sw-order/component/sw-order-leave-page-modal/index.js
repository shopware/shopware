import { Component } from 'src/core/shopware';
import template from './sw-order-leave-page-modal.html.twig';

Component.register('sw-order-leave-page-modal', {
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
