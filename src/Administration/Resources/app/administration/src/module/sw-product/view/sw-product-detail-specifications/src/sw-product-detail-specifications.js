/*
 * @sw-package inventory
 */

import template from './sw-product-detail-specifications.html.twig';
import { aclService, featureService, repositoryFactoryService, useProductDetailStore } from '../dependencies';

/**
 * @public
 */
export default {
    template,

    inject: [aclService, featureService, repositoryFactoryService],

    data() {
        return {
            showMediaModal: false,
        };
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

        isLoading() {
            return useProductDetailStore().isLoading;
        },

        customFieldSets() {
            return useProductDetailStore().customFieldSets;
        },

        showModeSetting() {
            return useProductDetailStore().showModeSetting;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, use `productType` instead.
         */
        productStates() {
            return useProductDetailStore().productStates;
        },

        productType() {
            return useProductDetailStore().productType;
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
            return useProductDetailStore().showProductCard(key);
        },
    },
};
