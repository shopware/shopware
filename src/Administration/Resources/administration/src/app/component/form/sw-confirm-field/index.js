import { Component } from 'src/core/shopware';
import template from './sw-confirm-field.html.twig';
import './sw-confirm-field.less';

Component.register('sw-confirm-field', {
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
        },

        vanish: {
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

        showActionButtons() {
            this.isEditing = true;
        },

        submitValue() {
            this.$emit('input', this.draft);
            this.removeActionButtons();
        },

        blurField(event) {
            if (event.relatedTarget && event.relatedTarget.classList.contains('sw-confirm-field__button')) {
                return;
            }
            this.cancelSubmit();
        },

        cancelSubmit() {
            this.draft = this.value;
            this.removeActionButtons();
        }
    }
});
