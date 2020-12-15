import template from './sw-select-rating.html.twig';

const { Component } = Shopware;

Component.extend('sw-select-rating', 'sw-text-field', {
    template,
    inheritAttrs: false,

    model: {
        prop: 'value',
        event: 'change'
    },

    methods: {
        onChange(value) {
            this.$emit('change', value);
        }
    }
});
