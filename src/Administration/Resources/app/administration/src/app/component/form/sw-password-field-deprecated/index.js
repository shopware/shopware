import template from './sw-password-field-deprecated.html.twig';
import './sw-password-field.scss';

/**
 * @package admin
 *
 * @private
 * @description password input field.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-password-field type="password" label="Name" placeholder="placeholder goes here..."></sw-password-field>
 */
Shopware.Component.extend('sw-password-field-deprecated', 'sw-text-field-deprecated', {
    template,

    props: {
        passwordToggleAble: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
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
