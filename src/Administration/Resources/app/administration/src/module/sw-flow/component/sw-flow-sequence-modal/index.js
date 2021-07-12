import template from './sw-flow-sequence-modal.html.twig';

const { Component } = Shopware;

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
