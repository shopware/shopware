/*
 * @sw-package inventory
 */

import template from './sw-product-detail-specifications.html.twig';
import useSwProductDetailStore from 'shopware:stores/swProductDetail';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
        'feature',
        'repositoryFactory',
    ],

    data() {
        return {
            showMediaModal: false,
        };
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

        isLoading() {
            return useSwProductDetailStore().isLoading;
        },

        customFieldSets() {
            return useSwProductDetailStore().customFieldSets;
        },

        showModeSetting() {
            return useSwProductDetailStore().showModeSetting;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, use `productType` instead.
         */
        productStates() {
            return useSwProductDetailStore().productStates;
        },

        productType() {
            return useSwProductDetailStore().productType;
        },

        isDigitalProduct() {
            return this.productType === 'digital' || this.productStates.includes('is-download');
        },

        customFieldsExists() {
            return !this.customFieldSets.length <= 0;
        },

        showCustomFieldsCard() {
            return this.showProductCard('custom_fields') && !this.isLoading && this.customFieldsExists;
        },
    },

    methods: {
        showProductCard(key) {
            return useSwProductDetailStore().showProductCard(key);
        },
    },
};
