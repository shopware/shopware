import template from './sw-gtc-checkbox.html.twig';

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
