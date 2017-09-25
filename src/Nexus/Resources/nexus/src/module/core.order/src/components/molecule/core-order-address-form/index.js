import template from './core-order-address-form.html.twig';

export default Shopware.ComponentFactory.register('core-order-address-form', {
    props: {
        address: {
            type: Object,
            required: true,
            default: {}
        },
        countries: {
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
