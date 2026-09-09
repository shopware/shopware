/*
 * @sw-package inventory
 */

import template from './sw-product-feature-set-form.html.twig';
import './sw-product-feature-set-form.scss';
import useSwProductDetailStore from 'shopware:stores/swProductDetail';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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
            return useSwProductDetailStore().product;
        },

        parentProduct() {
            return useSwProductDetailStore().parentProduct;
        },

        isLoading() {
            return useSwProductDetailStore().isLoading;
        },
    },
};
