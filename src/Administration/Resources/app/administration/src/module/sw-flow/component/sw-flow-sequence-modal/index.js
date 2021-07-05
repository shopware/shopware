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
        processSuccess() {
            this.$emit('process-finish', this.sequence);
        },

        onClose() {
            this.$emit('modal-close');
        },
    },
});
