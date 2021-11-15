import template from './sw-product-deliverability-form.html.twig';

const { Component, Mixin } = Shopware;
const { mapState, mapPropertyErrors, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('sw-product-deliverability-form', {
    template,

    inject: ['feature'],

    mixins: [
        Mixin.getByName('placeholder'),
    ],

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
            'loading',
        ]),

        ...mapGetters('swProductDetail', [
            'showModeSetting',
        ]),

        ...mapPropertyErrors('product', [
            'stock',
            'deliveryTimeId',
            'isCloseout',
            'maxPurchase',
            'purchaseSteps',
            'minPurchase',
            'shippingFree',
            'restockTime',
        ]),
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.feature.isActive('FEATURE_NEXT_17546') && typeof this.product.stock === 'undefined') {
                this.product.stock = 0;
            }
        },
    },
});
