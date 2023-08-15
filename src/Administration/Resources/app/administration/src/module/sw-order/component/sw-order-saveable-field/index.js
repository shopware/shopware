import template from './sw-order-saveable-field.html.twig';
import './sw-order-saveable-field.scss';

/**
 * @package customer-order
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        // FIXME: add type to value property
        // eslint-disable-next-line vue/require-prop-types
        value: {
            required: true,
            default: null,
        },
        type: {
            type: String,
            required: true,
            default: 'text',
        },
        // FIXME: add type to placeholder property
        // eslint-disable-next-line vue/require-prop-types
        placeholder: {
            required: false,
            default: null,
        },
        editable: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    data() {
        return {
            isEditing: false,
            isLoading: false,
        };
    },

    computed: {
        component() {
            switch (this.type) {
                case 'checkbox':
                    return 'sw-checkbox-field';
                case 'colorpicker':
                    return 'sw-colorpicker';
                case 'compactColorpicker':
                    return 'sw-compact-colorpicker';
                case 'date':
                    return 'sw-datepicker';
                case 'email':
                    return 'sw-email-field';
                case 'number':
                    return 'sw-number-field';
                case 'password':
                    return 'sw-password-field';
                case 'radio':
                    return 'sw-radio-field';
                case 'select':
                    return 'sw-select-field';
                case 'switch':
                    return 'sw-switch-field';
                case 'textarea':
                    return 'sw-textarea-field';
                case 'url':
                    return 'sw-url-field';
                default:
                    return 'sw-text-field';
            }
        },
    },

    methods: {
        onClick() {
            if (this.editable) {
                this.isEditing = true;
            }
        },

        onSaveButtonClicked() {
            this.isEditing = false;
            this.$emit('value-change', this.$refs['edit-field'].currentValue);
        },

        onCancelButtonClicked() {
            this.isEditing = false;
        },
    },
};
