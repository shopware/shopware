/*
 * @sw-package inventory
 */

import template from './sw-product-guarantee-form.html.twig';
import './sw-product-guarantee-form.scss';
import useSwProductDetailStore from 'shopware:stores/swProductDetail';

const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

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

        ...mapPropertyErrors('product', [
            'guaranteeMonths',
            'guaranteeConfirmed',
        ]),
    },
};
