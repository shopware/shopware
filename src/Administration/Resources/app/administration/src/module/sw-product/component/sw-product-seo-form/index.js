import template from './sw-product-seo-form.html.twig';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors, mapState, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('sw-product-seo-form', {
    template,

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            default: true
        }
    },

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

        ...mapPropertyErrors('product', [
            'keywords',
            'metaDescription',
            'metaTitle'
        ])
    }
});
