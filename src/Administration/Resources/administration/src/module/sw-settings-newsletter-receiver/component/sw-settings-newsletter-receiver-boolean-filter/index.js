import { Component } from 'src/core/shopware';
import template from './sw-settings-newsletter-receiver-boolean-filer.html.twig';

Component.register('sw-settings-newsletter-receiver-boolean-filter', {
    template,

    props: {
        id: {
            type: String,
            required: true
        },

        label: {
            type: String,
            required: false,
            default: ''
        },

        group: {
            type: String,
            required: false
        }
    },

    methods: {
        onChange(value) {
            this.$emit('change', { id: this.id, group: this.group, value });
        }
    }
});
