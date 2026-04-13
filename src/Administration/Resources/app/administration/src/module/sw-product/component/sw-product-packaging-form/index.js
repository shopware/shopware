/*
 * @sw-package inventory
 */

import template from './sw-product-packaging-form.html.twig';
import './sw-product-packaging-form.scss';

const { Mixin } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    mixins: [
        Mixin.getByName('placeholder'),
    ],

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
            return Shopware.Store.get('swProductDetail').product;
        },

        parentProduct() {
            return Shopware.Store.get('swProductDetail').parentProduct;
        },

        // @deprecated tag:v6.8.0 - will be removed due to unused
        isLoading() {
            return Shopware.Store.get('swProductDetail').isLoading;
        },

        ...mapPropertyErrors('product', [
            'purchaseUnit',
            'referenceUnit',
            'packUnit',
            'PackUnitPlural',
        ]),
    },
};
