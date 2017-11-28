import template from './core-order-basic-form.html.twig';

export default Shopware.Component.register('core-order-basic-form', {
    props: {
        order: {
            type: Object,
            required: true,
            default: {}
        },
        customers: {
            type: Array,
            required: true,
            default: []
        },
        shops: {
            type: Array,
            required: true,
            default: []
        },
        currencies: {
            type: Array,
            required: true,
            default: []
        },
        orderStates: {
            type: Array,
            required: true,
            default: []
        },
        paymentMethods: {
            type: Array,
            required: true,
            default: []
        },
        isWorking: {
            type: Boolean,
            required: true,
            default: false
        }
    },

    template
});
