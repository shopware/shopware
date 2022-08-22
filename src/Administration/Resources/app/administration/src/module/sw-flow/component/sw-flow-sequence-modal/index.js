import template from './sw-flow-sequence-modal.html.twig';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-flow-sequence-modal', {
    template,

    props: {
        sequence: {
            type: Object,
            required: true,
        },

        modalName: {
            type: String,
            required: true,
        },

        action: {
            type: String,
            required: false,
            default: null,
        },
    },

    methods: {
        processSuccess(data) {
            this.$emit('process-finish', data);
        },

        onClose() {
            this.$emit('modal-close');
        },
    },
});
