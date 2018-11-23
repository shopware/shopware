import { Component } from 'src/core/shopware';
import template from './sw-media-upload-url-modal.tml.twig';

/**
 * @private
 */
Component.register('sw-media-upload-url-modal', {
    template,

    data() {
        return {
            url: '',
            hasError: false,
            missingFileExtension: false,
            fileExtension: ''
        };
    },

    computed: {
        swFieldErrorClass() {
            return {
                'has--error': this.hasError
            };
        }
    },

    methods: {
        validateUrl() {
            try {
                const tmp = new URL(this.url);
                tmp.searchParams.get('id');
                this.hasError = false;
            } catch (e) {
                this.hasError = true;
            }
        },

        checkFileExtension() {
            if (this.hasError) {
                return;
            }

            const url = new URL(this.url);
            if (this.fileExtension === '' && url.pathname.split('.').length <= 1) {
                this.missingFileExtension = true;
                return;
            }
            this.missingFileExtension = false;

            this.fileExtension = url.pathname.split('.').pop();
        },

        emitUrl(originalDomEvent) {
            if (this.hasError === false && this.fileExtension !== '') {
                this.$emit('sw-media-upload-url-modal-submit', {
                    originalDomEvent,
                    url: new URL(this.url),
                    fileExtension: this.fileExtension
                });
                this.closeModal();
            }
        },

        closeModal() {
            this.$emit('closeModal');
        }
    }
});
