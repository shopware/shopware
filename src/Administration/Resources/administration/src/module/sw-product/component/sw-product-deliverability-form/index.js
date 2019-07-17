import { Component, Mixin } from 'src/core/shopware';
import { mapApiErrors } from 'src/app/service/map-errors.service';
import { mapState } from 'vuex';
import template from './sw-product-deliverability-form.html.twig';

Component.register('sw-product-deliverability-form', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'loading'
        ]),

        ...mapApiErrors('product', [
            'stock',
            'deliveryTimeId',
            'isCloseout',
            'maxPurchase',
            'purchaseSteps',
            'minPurchase',
            'shippingFree',
            'restockTime'
        ])
    }
});
