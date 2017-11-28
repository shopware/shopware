import template from './core-product-basic-form.html.twig';

export default Shopware.Component.register('core-product-basic-form', {
    props: {
        product: {
            type: Object,
            required: true,
            default: {}
        },
        manufacturers: {
            type: Array,
            required: true,
            default: []
        },
        isWorking: {
            type: Boolean,
            required: true,
            default: false
        },
        serviceProvider: {
            type: Object,
            required: true
        }
    },

    template
});
