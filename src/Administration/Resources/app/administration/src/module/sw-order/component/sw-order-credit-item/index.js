import template from './sw-order-credit-item.html.twig';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-order-credit-item', {
    template,

    props: {
        credit: {
            type: Object,
            required: true,
        },

        currency: {
            type: Object,
            required: true,
        },
    },

    methods: {
        onChangeCreditPrice(value) {
            this.$set(this.credit, 'price', Math.abs(value) * -1);
        },
    },
});
