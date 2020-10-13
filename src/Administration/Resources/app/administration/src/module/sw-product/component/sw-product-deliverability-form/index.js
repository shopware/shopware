import template from './sw-product-deliverability-form.html.twig';

const { Component, Mixin } = Shopware;
const { mapState, mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-product-deliverability-form', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            default: true
        }
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'loading'
        ]),

        ...mapPropertyErrors('product', [
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
