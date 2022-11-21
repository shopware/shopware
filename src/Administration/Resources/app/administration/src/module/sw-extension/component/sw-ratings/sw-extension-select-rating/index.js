import template from './sw-extension-select-rating.html.twig';

/**
 * @private
 */
export default {
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
};
