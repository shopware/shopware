import template from './sw-category-entry-point-overwrite-modal.html.twig';
import './sw-category-entry-point-overwrite-modal.scss';

const { Component } = Shopware;

Component.register('sw-category-entry-point-overwrite-modal', {
    template,

    props: {
        salesChannels: {
            type: Array,
            required: false,
            default: () => {
                return [];
            },
        },
    },

    methods: {
        onCancel() {
            this.$emit('cancel');
        },

        onConfirm() {
            this.$emit('confirm');
        },
    },
});
