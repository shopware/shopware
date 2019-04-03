import { Component } from 'src/core/shopware';
import template from './sw-order-saveable-field.html.twig';
import './sw-order-saveable-field.scss';

Component.register('sw-order-saveable-field', {
    template,
    props: {
        value: {
            required: true,
            default: null
        },
        type: {
            type: String,
            required: true,
            default: 'text'
        },
        placeholder: {
            required: false,
            default: null
        },
        editable: {
            type: Boolean,
            required: false,
            default: true
        }
    },
    data() {
        return {
            isEditing: false,
            isLoading: false
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
            if (this.type !== 'select') {
                this.$emit('valueChanged', this.$refs['edit-field'].currentValue);
            } else {
                this.$emit('valueChanged', this.$refs['edit-field'].singleSelection);
            }
        },
        onCancelButtonClicked() {
            this.isEditing = false;
        },
        displayValue() {
            let retVal = '';
            if (this.type === 'number') {
                if (this.value !== null) {
                    retVal = this.value;
                }
            }
            if (this.placeholder) {
                retVal = this.placeholder;
            }

            return retVal;
        }
    }

});
