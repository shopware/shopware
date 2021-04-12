import template from './sw-product-detail-specifications.html.twig';

const { Component } = Shopware;
const { mapState, mapGetters } = Component.getComponentHelper();

Component.register('sw-product-detail-specifications', {
    template,

    inject: ['acl'],

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'customFieldSets'
        ]),

        ...mapGetters('swProductDetail', [
            'isLoading',
            'showModeSetting',
            'showProductCard'
        ]),

        showCustomFieldsCard() {
            return this.showProductCard('custom_fields') && !this.isLoading && this.customFieldSets.length > 0;
        }
    }
});
