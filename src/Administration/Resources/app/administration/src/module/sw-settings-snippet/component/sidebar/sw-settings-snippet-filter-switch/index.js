import template from './sw-settings-snippet-filter-switch.html.twig';
import './sw-settings-snippet-filter-switch.scss';

const { Component } = Shopware;

Component.register('sw-settings-snippet-filter-switch', {
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

        group: {
            type: String,
            required: false
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
                'sw-settings-snippet-filter-switch__field',
                `sw-settings-snippet-filter-switch--${this.type}`,
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
            const group = this.group;
            this.$emit('change', { value, name, group });
        }
    }
});
