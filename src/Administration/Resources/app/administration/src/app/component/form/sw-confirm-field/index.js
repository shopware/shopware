import template from './sw-confirm-field.html.twig';
import './sw-confirm-field.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @description Text field with additional confirmation buttons inlined in the field itself.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-confirm-field placeholder="Enter value..."></sw-confirm-field>
 */
Component.register('sw-confirm-field', {
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        value: {
            type: String,
            required: false,
            default: '',
        },

        compact: {
            type: Boolean,
            required: false,
            default: false,
        },

        preventEmptySubmit: {
            type: Boolean,
            required: false,
            default: false,
        },

        required: {
            type: Boolean,
            required: false,
            default: false,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        error: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            hasSubmittedFromKey: false,
            isEditing: false,
            draft: this.value,
            event: null,
        };
    },

    computed: {
        confirmFieldClasses() {
            return {
                'sw-confirm-field--compact': this.compact,
                'sw-confirm-field--editing': this.isEditing,
                'has--error': !!this.error,
            };
        },
    },

    watch: {
        value() {
            this.draft = this.value;
        },
    },

    beforeUnmount() {
        this.$emit('remove-error');
    },

    methods: {
        removeActionButtons() {
            this.isEditing = false;
        },

        onStartEditing() {
            this.isEditing = true;
        },

        onBlurField(event) {
            if (event?.relatedTarget?.classList.contains('sw-confirm-field__button') || this.hasSubmittedFromKey) {
                this.hasSubmittedFromKey = false;
                return;
            }
            this.$emit('blur');
            this.cancelSubmit();
        },

        cancelSubmit() {
            this.removeActionButtons();
            this.draft = this.value;
        },

        onCancelFromKey({ target }) {
            this.cancelSubmit();
            target.blur();
        },

        onCancelSubmit() {
            this.$emit('submit-cancel');
            this.cancelSubmit();
            this.isEditing = false;
        },

        submitValue() {
            if (this.draft !== this.value) {
                this.$emit('input', this.draft, this.event);
            }
        },

        onSubmitFromKey() {
            this.hasSubmittedFromKey = true;
            this.event = 'key';
            this.submitValue();
            this.isEditing = false;
        },

        onSubmitValue() {
            this.event = 'click';
            this.submitValue();
            this.isEditing = false;
        },

        onInput() {
            this.$emit('remove-error');
        },
    },
});
