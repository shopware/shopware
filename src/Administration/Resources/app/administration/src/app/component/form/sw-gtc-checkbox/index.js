/**
 * @package admin
 */

import template from './sw-gtc-checkbox.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Component.register('sw-gtc-checkbox', {
    template,

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
            this.$emit('change', value);
        },
    },
});
