/*
 * @package inventory
 */

import template from './sw-product-detail-specifications.html.twig';

const { Component } = Shopware;
const { mapState, mapGetters } = Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl', 'feature', 'repositoryFactory'],

    data() {
        return {
            showMediaModal: false,
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'customFieldSets',
            'loading',
        ]),

        ...mapGetters('swProductDetail', [
            'isLoading',
            'showModeSetting',
            'showProductCard',
            'productStates',
        ]),

        customFieldsExists() {
            return !this.customFieldSets.length <= 0;
        },

        showCustomFieldsCard() {
            return this.showProductCard('custom_fields') &&
                !this.isLoading &&
                this.customFieldsExists;
        },
    },
};
