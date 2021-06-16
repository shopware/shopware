import template from './sw-extension-domains-modal.html.twig';
import './sw-extension-domains-modal.scss';

const { Component } = Shopware;

Component.register('sw-extension-domains-modal', {
    template,

    props: {
        extensionLabel: {
            type: String,
            required: true,
        },

        domains: {
            type: Array,
            required: true,
        },
    },

    computed: {
        modalTitle() {
            return this.$t(
                'sw-extension-store.component.sw-extension-domains-modal.modalTitle',
                { extensionLabel: this.extensionLabel },
            );
        },
    },

    methods: {
        close() {
            this.$emit('modal-close');
        },
    },
});
