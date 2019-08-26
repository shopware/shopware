import template from './sw-product-settings-form.html.twig';

const { Component } = Shopware;
const { mapApiErrors, mapState } = Shopware.Component.getComponentHelper();

Component.register('sw-product-settings-form', {
    template,

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct'
        ]),

        ...mapApiErrors('product', [
            'releaseDate',
            'stock',
            'minPurchase',
            'maxPurchase',
            'ean',
            'manufacturerNumber',
            'shippingFree',
            'markAsTopseller'
        ])
    }
});
