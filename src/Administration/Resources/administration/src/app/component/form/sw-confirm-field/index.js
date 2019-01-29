import template from './sw-confirm-field.html.twig';
import './sw-confirm-field.scss';

export default {
    name: 'sw-confirm-field',
    template,

    props: {
        value: {
            type: String,
            required: false,
            default: ''
        },

        compact: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            isEditing: false,
            draft: this.value
        };
    },

    computed: {
        additionalAttributes() {
            return Object.assign({}, this.$attrs, { type: 'text' });
        },

        confirmFieldClasses() {
            return {
                'sw-confirm-field--compact': this.compact,
                'sw-confirm-field--editing': this.isEditing
            };
        }
    },

    watch: {
        value() {
            this.draft = this.value;
        }
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

            this.cancelSubmit();
            this.removeActionButtons();
        },

        cancelSubmit() {
            this.draft = this.value;
        },

        onCancelFromKey({ target }) {
            this.cancelSubmit();
            target.blur();
        },

        onCancelSubmit() {
            this.cancelSubmit();
            this.isEditing = false;
        },

        submitValue() {
            if (this.draft !== this.value) {
                this.$emit('input', this.draft);
            }
        },

        onSubmitFromKey({ target }) {
            this.submitValue();
            target.blur();
            this.isEditing = false;
        },

        onSubmitValue() {
            this.submitValue();
            this.isEditing = false;
        }
    }
};
