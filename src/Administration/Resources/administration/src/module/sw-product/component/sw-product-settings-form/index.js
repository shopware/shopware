import { Component } from 'src/core/shopware';
import { mapState } from 'vuex';
import { mapFormErrors } from 'src/app/service/map-errors.service';
import template from './sw-product-settings-form.html.twig';

Component.register('sw-product-settings-form', {
    template,

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct'
        ]),

        ...mapFormErrors('product', [
            'releaseDate',
            'stock',
            'width',
            'height',
            'length',
            'weight',
            'minPurchase',
            'maxPurchase',
            'ean',
            'manufacturerNumber',
            'shippingFree',
            'markAsTopseller'
        ])
    }
});
