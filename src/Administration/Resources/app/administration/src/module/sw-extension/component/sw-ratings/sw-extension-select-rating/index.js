import template from './sw-extension-select-rating.html.twig';

/**
 * @package checkout
 * @private
 */
export default {
    template,
    inheritAttrs: false,

    inject: ['feature'],

    methods: {
        onChange(value) {
            this.$emit('update:value', value);
        },
    },
};
