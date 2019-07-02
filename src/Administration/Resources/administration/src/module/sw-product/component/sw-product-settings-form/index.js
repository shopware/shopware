import { Component } from 'src/core/shopware';
import template from './sw-product-settings-form.html.twig';

const { mapState, mapApiErrors } = Component.getComponentHelper();

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
