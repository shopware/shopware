import './sw-error.scss';
import template from './sw-error.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * @public
 * @description
 * Renders a error page.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-error :errorObject="{ message: 'Could not load the page' }">
 * </sw-error>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-error', {
    template,

    props: {
        errorObject: {
            type: Object,
            required: false,
            default() {
                return {};
            },
        },
        routerLink: {
            type: Object,
            required: false,
            default() {
                return {};
            },
        },
        linkText: {
            type: String,
            required: false,
            default: '',
        },
    },

    computed: {
        error() {
            if (Object.keys(this.errorObject).length > 0) {
                return this.errorObject;
            }
            return this.$root.initError;
        },

        imagePath() {
            return '/administration/static/img/error.svg';
        },

        message() {
            if (!this.error.message) {
                return this.$tc('sw-error.general.messagePlaceholder');
            }
            return this.error.message;
        },

        statusCode() {
            if (!this.error.response) {
                return this.$tc('global.default.error');
            }

            return this.error.response.status;
        },

        showStack() {
            return process.env.NODE_ENV === 'development' && this.error.stack;
        },

        showLink() {
            return Object.keys(this.routerLink).length > 0;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.linkText) {
                // eslint-disable-next-line vue/no-mutating-props
                this.linkText = this.$tc('sw-error.general.textLink');
            }
        },
    },
});
