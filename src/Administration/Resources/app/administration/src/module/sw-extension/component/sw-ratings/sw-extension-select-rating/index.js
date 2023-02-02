import template from './sw-extension-select-rating.html.twig';

const { Component } = Shopware;

/**
 * @private
 */
Component.extend('sw-extension-select-rating', 'sw-text-field', {
    template,
    inheritAttrs: false,

    model: {
        prop: 'value',
        event: 'change',
    },

    methods: {
        onChange(value) {
            this.$emit('change', value);
        },
    },
});
