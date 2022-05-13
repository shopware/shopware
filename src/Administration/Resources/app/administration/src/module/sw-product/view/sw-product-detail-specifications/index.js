import template from './sw-product-detail-specifications.html.twig';

const { Component } = Shopware;
const { mapState, mapGetters } = Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl', 'feature'],

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'customFieldSets',
        ]),

        ...mapGetters('swProductDetail', [
            'isLoading',
            'showModeSetting',
            'showProductCard',
        ]),

        customFieldsExists() {
            if (!this.customFieldSets.length > 0) {
                return false;
            }

            return true;
        },

        showCustomFieldsCard() {
            return this.showProductCard('custom_fields') &&
                !this.isLoading &&
                this.customFieldsExists;
        },
    },
};
