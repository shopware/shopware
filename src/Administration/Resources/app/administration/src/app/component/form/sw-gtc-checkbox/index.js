import template from './sw-gtc-checkbox.html.twig';
import './sw-gtc-checkbox.scss';

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
