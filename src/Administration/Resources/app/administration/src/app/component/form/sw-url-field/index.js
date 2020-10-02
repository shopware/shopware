import template from './sw-url-field.html.twig';
import './sw-url-field.scss';

const { Component } = Shopware;
const { ShopwareError } = Shopware.Classes;

/**
 * @public
 * @description Url field component which supports a switch for https and http.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-field type="url" label="Name" placeholder="Placeholder"
 * switchLabel="My shop uses https"></sw-field>
 */
Component.extend('sw-url-field', 'sw-text-field', {
    template,
    inheritAttrs: false,

    props: {
        error: {
            type: Object,
            required: false
        }
    },

    data() {
        return {
            sslActive: true,
            currentValue: this.value || '',
            errorUrl: null
        };
    },

    computed: {
        prefixClass() {
            if (this.sslActive) {
                return 'is--ssl';
            }

            return '';
        },

        urlPrefix() {
            if (this.sslActive) {
                return 'https://';
            }

            return 'http://';
        },

        url() {
            const trimmedValue = this.currentValue.trim();
            if (trimmedValue === '') {
                return '';
            }

            return `${this.urlPrefix}${trimmedValue}`;
        },

        combinedError() {
            return this.errorUrl || this.error;
        }
    },

    watch: {
        value() {
            this.checkInput(this.value || '');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.checkInput(this.currentValue);
        },

        onInput(event) {
            this.checkInput(event.target.value);
            this.$emit('input', this.url);
        },

        checkInput(inputValue) {
            if (inputValue.match(/^\s*https?:\/\//) !== null) {
                const sslFound = inputValue.match(/^\s*https:\/\//);
                this.sslActive = (sslFound !== null);
                this.currentValue = inputValue.replace(/^\s*https?:\/\//, '');
            } else {
                this.currentValue = inputValue;
            }

            this.validateCurrentValue();
        },

        validateCurrentValue() {
            if (this.currentValue) {
                try {
                    const url = new URL(`${this.urlPrefix}${this.currentValue}`);
                    const path = this.currentValue.endsWith('/') ? url.pathname : url.pathname.replace(/\/$/, '');
                    this.currentValue = url.hostname + path;
                    this.errorUrl = null;
                } catch {
                    this.errorUrl = new ShopwareError({
                        code: 'INVALID_URL'
                    });
                }
            }
        },

        changeMode(disabled) {
            if (disabled) {
                return;
            }

            this.sslActive = !this.sslActive;
            this.$emit('input', this.url);
        }
    }
});
