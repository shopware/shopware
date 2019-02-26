import { Component } from 'src/core/shopware';
import template from './sw-order-inline-field.html.twig';

Component.register('sw-order-inline-field', {
    template,

    props: {
        value: {
            type: String,
            required: false,
            default: ''
        },
        displayValue: {
            type: String,
            required: true,
            default: ''
        },
        editable: {
            type: Boolean,
            required: true,
            default: false
        }
    },
    methods: {
        onInput(value) {
            this.$emit('input', value);
        }
    }
});
