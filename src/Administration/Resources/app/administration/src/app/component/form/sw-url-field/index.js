import template from './sw-url-field.html.twig';
import './sw-url-field.scss';

const { Component } = Shopware;

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

    data() {
        return {
            sslActive: true,
            currentValue: this.value || ''
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

        onChange(event) {
            this.checkInput(event.target.value);
            this.$emit('change', this.url);
        },

        checkInput(inputValue) {
            if (inputValue.match(/^\s*https?:\/\//) !== null) {
                const sslFound = inputValue.match(/^\s*https:\/\//);
                this.sslActive = (sslFound !== null);
            }

            this.currentValue = inputValue.replace(/^\s*https?:\/\//, '');
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
