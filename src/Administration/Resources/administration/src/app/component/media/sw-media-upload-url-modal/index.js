import { Component } from 'src/core/shopware';
import template from './sw-media-upload-url-modal.tml.twig';

Component.register('sw-media-upload-url-modal', {
    template,

    data() {
        return {
            url: ''
        };
    },

    methods: {
        emitUrl(originalDomEvent) {
            this.$emit('sw-media-upload-url-modal-submit', {
                originalDomEvent,
                url: this.url
            });
            this.closeModal();
        },

        closeModal() {
            this.$emit('closeModal');
        }
    }
});
