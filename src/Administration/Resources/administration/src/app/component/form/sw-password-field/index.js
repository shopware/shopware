import template from './sw-password-field.html.twig';
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
    extendsFrom: 'sw-text-field',
    template,

    props: {
        passwordToggleAble: {
            type: Boolean,
            required: false,
            default: true
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
        onTogglePasswordVisibility() {
            this.showPassword = !this.showPassword;
        }
    }
};
