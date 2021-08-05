import template from './sw-confirm-field.html.twig';
import './sw-confirm-field.scss';

const { Component } = Shopware;

/**
 * @public
 * @description Text field with additional confirmation buttons inlined in the field itself.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-confirm-field placeholder="Enter value..."></sw-confirm-field>
 */
Component.register('sw-confirm-field', {
    template,

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
    },

    data() {
        return {
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
            };
        },
    },

    watch: {
        value() {
            this.draft = this.value;
        },
    },

    methods: {
        removeActionButtons() {
            this.isEditing = false;
        },

        onStartEditing() {
            this.isEditing = true;
        },

        onBlurField({ relatedTarget }) {
            if (!!relatedTarget && relatedTarget.classList.contains('sw-confirm-field__button')) {
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

        onSubmitFromKey({ target }) {
            this.event = 'key';
            this.submitValue();
            target.blur();
            this.isEditing = false;
        },

        onSubmitValue() {
            this.event = 'click';
            this.submitValue();
            this.isEditing = false;
        },
    },
});
