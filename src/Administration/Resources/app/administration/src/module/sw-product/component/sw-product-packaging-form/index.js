/*
 * @sw-package inventory
 */

import template from './sw-product-packaging-form.html.twig';

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
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        showSettingPackaging: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
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

        isLoading() {
            return Shopware.Store.get('swProductDetail').isLoading;
        },

        ...mapPropertyErrors('product', [
            'purchaseUnit',
            'referenceUnit',
            'packUnit',
            'PackUnitPlural',
            'width',
            'height',
            'length',
            'weight',
        ]),
    },
};
