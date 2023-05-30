import template from './sw-url-field.html.twig';
import './sw-url-field.scss';

const { Component } = Shopware;
const { ShopwareError } = Shopware.Classes;

const URL_REGEX = {
    PROTOCOL: /([a-zA-Z0-9]+\:\/\/)+/,
    PROTOCOL_HTTP: /^https?:\/\//,
    SSL: /^\s*https:\/\//,
    TRAILING_SLASH: /\/+$/,
};

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * @public
 * @description URL field component which supports a switch for https and http.
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
            currentDebounce: null,
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

        onBlur(event) {
            this.checkInput(event.target.value);
        },

        checkInput(inputValue) {
            this.errorUrl = null;

            if (!inputValue.length) {
                this.handleEmptyUrl();

                return;
            }

            if (inputValue.match(URL_REGEX.PROTOCOL_HTTP)) {
                this.sslActive = this.getSSLMode(inputValue);
            }

            const validated = this.validateCurrentValue(inputValue);

            if (!validated) {
                this.setInvalidUrlError();
            } else {
                this.currentValue = validated;

                this.$emit('input', this.url);
            }
        },

        handleEmptyUrl() {
            this.currentValue = '';

            this.$emit('input', '');
        },

        validateCurrentValue(value) {
            const url = this.getURLInstance(value);

            // If the input is invalid, no URL can be constructed
            if (!url) {
                return null;
            }

            if (this.omitUrlSearch) {
                url.search = '';
            }

            if (this.omitUrlHash) {
                url.hash = '';
            }

            // when a hash or search query is provided we want to allow trailing slash, eg a vue route `admin#/`
            const removeTrailingSlash = url.hash === '' && url.search === '' ? URL_REGEX.TRAILING_SLASH : '';

            // build URL via native URL.toString() function instead by hand @see NEXT-15747
            return url
                .toString()
                .replace(URL_REGEX.PROTOCOL, '')
                .replace(removeTrailingSlash, '')
                .replace(url.host, this.$options.filters.unicodeUri(url.host));
        },

        changeMode(disabled) {
            if (disabled) {
                return;
            }

            this.sslActive = !this.sslActive;
            this.$emit('input', this.url);
        },

        getURLInstance(value) {
            try {
                const url = value.match(URL_REGEX.PROTOCOL) ? value : `${this.urlPrefix}${value}`;

                return new URL(url);
            } catch {
                this.setInvalidUrlError();

                return null;
            }
        },

        getSSLMode(value) {
            return !!value.match(URL_REGEX.SSL);
        },

        setInvalidUrlError() {
            this.errorUrl = new ShopwareError({
                code: 'INVALID_URL',
            });
        },
    },
});
