

import { Component } from 'src/core/shopware';
import template from './sw-order-saveable-field.html.twig';
import './sw-order-saveable-field.less';

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
        selectStore: {
            required: false,
            default: null
        },
        editable: {
            type: Boolean,
            required: false,
            default: true
        },
        truncateable: {
            type: Boolean,
            required: false,
            default: false
        }
    },
    data() {
        return {
            isEditing: false,
            isLoading: false
        };
    },
    computed: {
        textClasses() {
            if (this.truncateable) {
                return 'truncateable';
            }
            return '';
        }
    },
    methods: {
        onDoubleClick() {
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
            if (this.type === 'text') {
                if (this.value) {
                    retVal = this.value;
                }
            } else if (this.type === 'number') {
                if (this.value !== null) {
                    retVal = this.value;
                }
            } else if (this.type === 'select') {
                if (this.value) {
                    retVal = this.value.meta.viewData.name;
                }
            }

            if (this.placeholder) {
                retVal = this.placeholder;
            }

            return retVal;
        }
    }

});
