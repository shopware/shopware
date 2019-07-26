import template from './sw-password-field.html.twig';
import SwTextField from '../sw-text-field/index';
import './sw-password-field.scss';

/**
 * @public
 * @description password input field.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-password-field type="password" label="Name" placeholder="placeholder goes here..."></sw-password-field>
 */
export default {
    name: 'sw-password-field',
    extendsFrom: SwTextField.name,
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
};
