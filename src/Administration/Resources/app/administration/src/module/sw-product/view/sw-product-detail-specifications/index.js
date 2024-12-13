/*
 * @package inventory
 */

import template from './sw-product-detail-specifications.html.twig';

const { Component } = Shopware;
const { mapVuexState, mapVuexGetters } = Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

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
        ...mapVuexState('swProductDetail', [
            'product',
            'parentProduct',
            'customFieldSets',
            'loading',
        ]),

        ...mapVuexGetters('swProductDetail', [
            'isLoading',
            'showModeSetting',
            'showProductCard',
            'productStates',
        ]),

        customFieldsExists() {
            return !this.customFieldSets.length <= 0;
        },

        showCustomFieldsCard() {
            return this.showProductCard('custom_fields') && !this.isLoading && this.customFieldsExists;
        },
    },
};
