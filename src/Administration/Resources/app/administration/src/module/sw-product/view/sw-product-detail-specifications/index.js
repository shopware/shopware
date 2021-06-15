import template from './sw-product-detail-specifications.html.twig';

const { Component } = Shopware;
const { mapState, mapGetters } = Component.getComponentHelper();

Component.register('sw-product-detail-specifications', {
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

            return this.customFieldSets.some(set => set.customFields.length > 0);
        },

        showCustomFieldsCard() {
            return this.showProductCard('custom_fields') &&
                !this.isLoading &&
                this.customFieldsExists;
        },
    },
});
