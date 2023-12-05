import template from './sw-extension-select-rating.html.twig';

/**
 * @package services-settings
 * @private
 */
export default {
    template,
    inheritAttrs: false,

    inject: ['feature'],

    model: {
        prop: 'value',
        event: 'change',
    },

    methods: {
        onChange(value) {
            this.$emit('update:value', value);
        },
    },
};
