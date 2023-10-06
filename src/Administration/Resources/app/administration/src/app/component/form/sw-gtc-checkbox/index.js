/**
 * @package admin
 */

import template from './sw-gtc-checkbox.html.twig';

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Shopware.Component.register('sw-gtc-checkbox', {
    template,

    inject: ['feature'],

    model: {
        prop: 'value',
        event: 'change',
    },

    props: {
        value: {
            type: Boolean,
            required: true,
        },
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
});
