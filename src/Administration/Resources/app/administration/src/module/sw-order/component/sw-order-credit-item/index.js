import template from './sw-order-credit-item.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
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
};
