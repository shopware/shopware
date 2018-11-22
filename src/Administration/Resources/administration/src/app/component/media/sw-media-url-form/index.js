import { Component } from 'src/core/shopware';
import template from './sw-media-url-form.html.twig';

Component.register('sw-media-url-form', {
    template,

    props: {
        variant: {
            Type: String,
            required: true,
            validator(value) {
                return ['modal', 'inline'].includes(value);
            }
        }
    },

    data() {
        return {
            url: '',
            extensionFromUrl: '',
            extensionFromInput: ''
        };
    },

    computed: {
        swFieldErrorClass() {
            return {
                'has--error': this.hasInvalidInput
            };
        },

        urlObject() {
            try {
                return new URL(this.url);
            } catch (e) {
                this.extensionFromUrl = '';
                return null;
            }
        },

        hasInvalidInput() {
            return this.urlObject === null && this.url !== '';
        },

        missingFileExtension() {
            return this.urlObject !== null && !this.extensionFromUrl;
        },

        fileExtension() {
            return this.extensionFromUrl || this.extensionFromInput;
        },

        isValid() {
            return this.urlObject !== null && this.fileExtension;
        }
    },

    watch: {
        urlObject() {
            if (this.urlObject === null) {
                this.extensionFromUrl = '';
                return;
            }

            const fileName = this.urlObject.pathname.split('/').pop();
            if (fileName.split('.').length === 1) {
                this.extensionFromUrl = '';
                return;
            }

            this.extensionFromUrl = fileName.split('.').pop();
        }
    },

    methods: {
        emitUrl(originalDomEvent) {
            if (this.isValid) {
                this.$emit('sw-media-url-form-submit', {
                    originalDomEvent,
                    url: this.urlObject,
                    fileExtension: this.fileExtension
                });

                if (this.variant === 'modal') {
                    this.closeModal();
                }
            }
        },

        closeModal() {
            this.$emit('closeModal');
        }
    }
});
