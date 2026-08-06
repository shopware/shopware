/*
 * @sw-package inventory
 */

import template from './sw-product-guarantee-form.html.twig';
import './sw-product-guarantee-form.scss';

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
            return Shopware.Store.get('swProductDetail').product;
        },

        parentProduct() {
            return Shopware.Store.get('swProductDetail').parentProduct;
        },

        ...mapPropertyErrors('product', [
            'guaranteeMonths',
            'guaranteeConfirmed',
        ]),
    },
};
