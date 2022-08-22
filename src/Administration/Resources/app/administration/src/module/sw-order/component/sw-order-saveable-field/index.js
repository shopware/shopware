import template from './sw-order-saveable-field.html.twig';
import './sw-order-saveable-field.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-order-saveable-field', {
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
});
