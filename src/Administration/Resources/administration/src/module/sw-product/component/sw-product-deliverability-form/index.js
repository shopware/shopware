import { Component, Mixin } from 'src/core/shopware';
import template from './sw-product-deliverability-form.html.twig';

const { mapState, mapApiErrors } = Component.getComponentHelper();

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
