/*
 * @sw-package inventory
 */

import template from './sw-product-packaging-form.html.twig';
import './sw-product-packaging-form.scss';
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
            required: true,
        },

        showSettingPackaging: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        product() {
            return useProductDetailStore().product;
        },

        parentProduct() {
            return useProductDetailStore().parentProduct;
        },

        // @deprecated tag:v6.8.0 - will be removed due to unused
        isLoading() {
            return useProductDetailStore().isLoading;
        },

        ...mapPropertyErrors('product', [
            'purchaseUnit',
            'referenceUnit',
            'packUnit',
            'PackUnitPlural',
        ]),
    },
};
