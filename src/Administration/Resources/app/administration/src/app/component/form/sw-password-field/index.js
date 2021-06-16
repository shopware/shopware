import template from './sw-password-field.html.twig';
import './sw-password-field.scss';

const { Component } = Shopware;

/**
 * @public
 * @description password input field.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-password-field type="password" label="Name" placeholder="placeholder goes here..."></sw-password-field>
 */
Component.extend('sw-password-field', 'sw-text-field', {
    template,

    props: {
        passwordToggleAble: {
            type: Boolean,
            required: false,
            default: true,
        },

        placeholderIsPassword: {
            type: Boolean,
            required: false,
            default: false,
        },

        autocomplete: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            showPassword: false,
        };
    },

    computed: {
        typeFieldClass() {
            return this.passwordToggleAble ? 'sw-field--password' : 'sw-field--password sw-field--password--untoggable';
        },

        passwordPlaceholder() {
            return this.showPassword ||
                !this.placeholderIsPassword ?
                this.placeholder :
                '*'.repeat(this.placeholder.length ? this.placeholder.length : 6);
        },
    },

    methods: {
        onTogglePasswordVisibility(disabled) {
            if (disabled) {
                return;
            }

            this.showPassword = !this.showPassword;
        },
    },
});
