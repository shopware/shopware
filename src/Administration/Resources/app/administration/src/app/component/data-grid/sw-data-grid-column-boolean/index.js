import template from './sw-data-grid-column-boolean.html.twig';
import './sw-data-grid-column-boolean.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-data-grid-column-boolean', {
    template,

    props: {
        isInlineEdit: {
            type: Boolean,
            required: false,
            default: false
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false
        },
        value: {
            required: true
        }
    },

    computed: {
        currentValue: {
            get() {
                return this.value;
            },

            set(newValue) {
                this.$emit('input', newValue);
            }
        }
    }
});
