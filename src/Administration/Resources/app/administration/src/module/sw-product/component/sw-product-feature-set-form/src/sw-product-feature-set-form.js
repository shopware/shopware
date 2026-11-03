/*
 * @sw-package inventory
 */

import template from './sw-product-feature-set-form.html.twig';
import './sw-product-feature-set-form.scss';
import { useProductDetailStore } from '../dependencies';

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

        isLoading() {
            return useProductDetailStore().isLoading;
        },
    },
};
