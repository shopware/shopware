/*
 * @sw-package inventory
 */

import placeholderMixin from 'shopware:mixins/placeholder';
import template from './sw-product-deliverability-form.html.twig';
import useSwProductDetailStore from 'shopware:stores/swProductDetail';

const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    mixins: [
        placeholderMixin,
    ],

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    computed: {
        product() {
            return useSwProductDetailStore().product;
        },

        parentProduct() {
            return useSwProductDetailStore().parentProduct;
        },

        loading() {
            return useSwProductDetailStore().loading;
        },

        showModeSetting() {
            return useSwProductDetailStore().showModeSetting;
        },

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
            if (typeof this.product.stock === 'undefined') {
                this.product.stock = 0;
            }
        },
    },
};
