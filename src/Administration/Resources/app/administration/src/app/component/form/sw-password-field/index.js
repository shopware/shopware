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
            default: true
        },

        autocomplete: {
            type: String,
            required: false
        }
    },

    data() {
        return {
            showPassword: false
        };
    },

    computed: {
        typeFieldClass() {
            return this.passwordToggleAble ? 'sw-field--password' : 'sw-field--password sw-field--password--untoggable';
        }
    },

    methods: {
        onTogglePasswordVisibility(disabled) {
            if (disabled) {
                return;
            }

            this.showPassword = !this.showPassword;
        }
    }
});
