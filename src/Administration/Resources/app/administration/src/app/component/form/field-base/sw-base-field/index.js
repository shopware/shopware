import template from './sw-base-field.html.twig';
import './sw-base-field.scss';

const { Component } = Shopware;
const utils = Shopware.Utils;

Component.register('sw-base-field', {
    template,
    inheritAttrs: false,

    props: {
        name: {
            type: String,
            required: false,
            default: null
        },

        label: {
            type: String,
            required: false,
            default: null
        },

        helpText: {
            type: String,
            required: false,
            default: null
        },

        isInvalid: {
            type: Boolean,
            required: false,
            default: false
        },

        error: {
            type: [Object],
            required: false,
            default() {
                return null;
            }
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false
        },

        required: {
            type: Boolean,
            required: false,
            default: false
        },

        isInherited: {
            type: Boolean,
            required: false,
            default: false
        },

        isInheritanceField: {
            type: Boolean,
            required: false,
            default: false
        },

        disableInheritanceToggle: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            id: utils.createId()
        };
    },

    computed: {
        identification() {
            if (this.name) {
                return this.name;
            }

            return `sw-field--${this.id}`;
        },

        hasLabel() {
            return !!this.label || !!this.helpText || this.isInheritanceField;
        },

        hasError() {
            return this.isInvalid || !!this.error;
        },

        swFieldClasses() {
            return {
                'has--error': this.hasError,
                'is--disabled': this.disabled,
                'is--inherited': this.isInherited
            };
        },

        swFieldLabelClasses() {
            return {
                'is--required': this.required
            };
        }
    }
});
