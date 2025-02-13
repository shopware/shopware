/*
 * @sw-package inventory
 */

import template from './sw-product-detail-specifications.html.twig';

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
            return Shopware.Store.get('swProductDetail').product;
        },

        parentProduct() {
            return Shopware.Store.get('swProductDetail').parentProduct;
        },

        loading() {
            return Shopware.Store.get('swProductDetail').loading;
        },

        isLoading() {
            return Shopware.Store.get('swProductDetail').isLoading;
        },

        customFieldSets() {
            return Shopware.Store.get('swProductDetail').customFieldSets;
        },

        showModeSetting() {
            return Shopware.Store.get('swProductDetail').showModeSetting;
        },

        productStates() {
            return Shopware.Store.get('swProductDetail').productStates;
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
            return Shopware.Store.get('swProductDetail').showProductCard(key);
        },
    },
};
