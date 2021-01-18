import template from './sw-product-detail-specifications.html.twig';

const { Component } = Shopware;
const { mapGetters } = Component.getComponentHelper();

Component.register('sw-product-detail-specifications', {
    template,

    inject: ['feature', 'acl'],

    computed: {
        ...mapGetters('swProductDetail', [
            'isLoading'
        ])
    }
});
