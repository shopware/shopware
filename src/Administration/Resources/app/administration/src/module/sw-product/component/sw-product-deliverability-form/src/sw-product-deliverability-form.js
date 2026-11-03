/*
 * @sw-package inventory
 */

import template from './sw-product-deliverability-form.html.twig';
import { mapPropertyErrors, placeholderMixin, useProductDetailStore } from '../dependencies';

/**
 * @public
 */
export default {
    template,

    mixins: [placeholderMixin],

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    computed: {
        product() {
            return useProductDetailStore().product;
        },

        parentProduct() {
            return useProductDetailStore().parentProduct;
        },

        loading() {
            return useProductDetailStore().loading;
        },

        showModeSetting() {
            return useProductDetailStore().showModeSetting;
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
