import template from './sw-product-deliverability-downloadable-form.html.twig';
import './sw-product-deliverability-downloadable-form.scss';

const { Component, Mixin } = Shopware;
const { mapState, mapPropertyErrors, mapGetters } = Shopware.Component.getComponentHelper();

/**
 * @private
 */
Component.register('sw-product-deliverability-downloadable-form', {
    template,

    mixins: [
        Mixin.getByName('placeholder'),
    ],

    props: {
        disabled: {
            type: Boolean,
            required: false,
            default: false,
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
        ]),
    },

    watch: {
        'product.isCloseout': {
            handler(newIsCloseout) {
                if (!newIsCloseout) {
                    this.product.stock = 0;
                }
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (typeof this.product.stock === 'undefined') {
                this.product.stock = 0;
            }
        },
    },
});
