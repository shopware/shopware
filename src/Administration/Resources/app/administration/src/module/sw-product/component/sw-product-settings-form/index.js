import template from './sw-product-settings-form.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors, mapState } = Shopware.Component.getComponentHelper();

Component.register('sw-product-settings-form', {
    template,

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
        ]),

        ...mapPropertyErrors('product', [
            'releaseDate',
            'stock',
            'minPurchase',
            'maxPurchase',
            'ean',
            'manufacturerNumber',
            'shippingFree',
            'markAsTopseller',
        ]),
    },
});
