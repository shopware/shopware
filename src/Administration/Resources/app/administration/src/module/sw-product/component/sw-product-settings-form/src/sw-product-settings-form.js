/*
 * @sw-package inventory
 */

import template from './sw-product-settings-form.html.twig';
import './sw-product-settings-form.scss';
import { mapPropertyErrors, useProductDetailStore } from '../dependencies';

/**
 * @public
 */
export default {
    template,

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

        ...mapPropertyErrors('product', [
            'releaseDate',
            'stock',
            'minPurchase',
            'maxPurchase',
            'ean',
            'manufacturerNumber',
            'shippingFree',
            'markAsTopseller',
        ]),
    },
};
