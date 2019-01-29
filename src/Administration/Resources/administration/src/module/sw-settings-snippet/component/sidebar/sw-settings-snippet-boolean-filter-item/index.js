import { Component } from 'src/core/shopware';
import template from './sw-settings-snippet-boolean-filer-item.html.twig';
import './sw-settings-snippet-boolean-filter-item.scss';

Component.register('sw-settings-snippet-boolean-filter-item', {
    template,

    props: {
        label: {
            type: String,
            required: false,
            default: ''
        },

        name: {
            type: String,
            required: true
        },

        borderTop: {
            type: Boolean,
            required: false,
            default: false
        },

        borderBottom: {
            type: Boolean,
            required: false,
            default: false
        },

        type: {
            type: String,
            required: false,
            default: 'small',
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['small', 'large'].includes(value);
            }
        }
    },

    computed: {
        fieldClasses() {
            return [
                'sw-settings-snippet-boolean-filter-item__field',
                `sw-settings-snippet-boolean-filter-item--${this.type}`,
                {
                    'border-top': this.borderTop,
                    'border-bottom': this.borderBottom
                }
            ].join(' ');
        }
    },

    methods: {
        onChange(value) {
            const name = this.name;
            this.$emit('change', { value, name });
        }
    }
});
