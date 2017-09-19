import template from './core-product-basic-form.html.twig';

export default Shopware.ComponentFactory.register('core-product-basic-form', {
    props: {
        product: {
            type: Object,
            required: true,
            default: {}
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
