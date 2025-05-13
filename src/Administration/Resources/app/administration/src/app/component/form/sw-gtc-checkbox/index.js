/**
 * @sw-package framework
 */

import template from './sw-gtc-checkbox.html.twig';

/**
 * @private
 */
export default {
    template,

    inject: ['feature'],

    emits: ['update:value'],

    props: {
        value: {
            type: Boolean,
            required: true,
        },
    },

    methods: {
        onChange(value) {
            this.$emit('update:value', value);
        },
    },
};
