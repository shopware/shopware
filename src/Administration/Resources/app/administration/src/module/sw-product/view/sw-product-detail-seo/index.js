import template from './sw-product-detail-seo.html.twig';

const { Component } = Shopware;
const { mapState, mapGetters } = Component.getComponentHelper();

Component.register('sw-product-detail-seo', {
    template,

    inject: ['feature', 'acl'],

    computed: {
        ...mapState('swProductDetail', [
            'product'
        ]),

        ...mapGetters('swProductDetail', [
            'isLoading'
        ])
    },

    methods: {
        onAddMainCategory(mainCategory) {
            if (this.product.mainCategories) {
                this.product.mainCategories.push(mainCategory);
            }
        }
    }
});
