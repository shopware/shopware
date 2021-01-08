import template from './sw-extension-gtc-checkbox.html.twig';
import './sw-extension-gtc-checkbox.scss';

const utils = Shopware.Utils;
const { Component } = Shopware;

Component.register('sw-extension-gtc-checkbox', {
    template,

    model: {
        prop: 'checked',
        event: 'change'
    },

    props: {
        checked: {
            type: Boolean,
            required: true
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        identification() {
            return `gtc-checkbox-${utils.createId()}`;
        }
    },

    methods: {
        onChange(value) {
            this.$emit('change', value.target.checked);
        }
    }
});
