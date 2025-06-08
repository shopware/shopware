import template from './sw-extension-select-rating.html.twig';

/**
 * @sw-package checkout
 * @private
 */
export default {
    template,
    inheritAttrs: false,

    emits: ['update:value'],

    methods: {
        onChange(value) {
            this.$emit('update:value', value);
        },
    },
};
