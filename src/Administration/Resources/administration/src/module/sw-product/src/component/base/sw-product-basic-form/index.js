import template from './sw-product-basic-form.html.twig';

Shopware.Component.register('sw-product-basic-form', {
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
