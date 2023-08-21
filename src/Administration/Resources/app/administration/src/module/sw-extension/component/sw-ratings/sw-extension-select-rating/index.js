import template from './sw-extension-select-rating.html.twig';

/**
 * @package merchant-services
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
            if (this.feature.isActive('VUE3')) {
                this.$emit('update:value', value);

                return;
            }

            this.$emit('change', value);
        },
    },
};
