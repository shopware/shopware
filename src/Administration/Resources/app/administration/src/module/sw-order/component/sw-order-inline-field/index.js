import './sw-order-inline-field.scss';
import template from './sw-order-inline-field.html.twig';

const { Component } = Shopware;

Component.register('sw-order-inline-field', {
    template,

    props: {
        value: {
            type: String,
            required: false,
            default: '',
        },
        displayValue: {
            type: String,
            required: true,
            default: '',
        },
        editable: {
            type: Boolean,
            required: true,
            default: false,
        },
        required: {
            type: Boolean,
            required: false,
            default: false,
        },
    },
    methods: {
        onInput(value) {
            this.$emit('input', value);
        },
    },
});
