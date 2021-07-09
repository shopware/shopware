import template from './sw-url-field.html.twig';
import './sw-url-field.scss';

const { Component, Utils } = Shopware;
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
            required: false,
            default: null,
        },
        omitUrlHash: {
            type: Boolean,
            default: false,
        },
        omitUrlSearch: {
            type: Boolean,
            default: false,
        },
    },

    data() {
        return {
            sslActive: true,
            currentValue: this.value || '',
            errorUrl: null,
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
        },
    },

    watch: {
        value() {
            this.checkInput(this.value || '');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.checkInput(this.currentValue);
        },

        /*
         * input handling is debounced to give the user a little time to enter a valid url
         * by direct-input-validation it is impossible to enter a url with port by typing
         */
        onInput: Utils.debounce(function debounceOnInput(event) {
            this.handleInput(event);
        }, 400),

        onBlur(event) {
            this.handleInput(event);
        },

        handleInput(event) {
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
                    if (this.omitUrlSearch) {
                        url.search = '';
                    }
                    if (this.omitUrlHash) {
                        url.hash = '';
                    }

                    // when a hash or search query is provided we want to allow trailing slash, eg a vue route `admin#/`
                    const removeTrailingSlash = url.hash === '' && url.search === '' ? /\/$/ : '';

                    // build URL via native URL.toString() function instead by hand @see NEXT-15747
                    this.currentValue = url
                        .toString()
                        .replace(/https?\:\/\//, '') // remove leading http|https
                        .replace(url.host, this.$options.filters.unicodeUri(url.host)) // fix "umlaut" domains
                        .replace(removeTrailingSlash, ''); // remove trailing slash
                    this.errorUrl = null;
                } catch {
                    this.errorUrl = new ShopwareError({
                        code: 'INVALID_URL',
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
        },
    },
});
