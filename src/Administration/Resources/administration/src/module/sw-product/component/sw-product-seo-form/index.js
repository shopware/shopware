import template from './sw-product-seo-form.html.twig';

const { Component, Mixin } = Shopware;
const { mapApiErrors, mapState, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('sw-product-seo-form', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    computed: {
        ...mapGetters('swProductDetail', [
            'isLoading'
        ]),

        ...mapState('swProductDetail', [
            'product',
            'parentProduct'
        ]),

        ...mapApiErrors('product', [
            'keywords',
            'metaDescription',
            'metaTitle'
        ])
    }
});
