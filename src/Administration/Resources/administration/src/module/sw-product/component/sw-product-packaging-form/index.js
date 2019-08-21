import { Component, Mixin } from 'src/core/shopware';
import template from './sw-product-packaging-form.html.twig';

const { mapState, mapGetters, mapApiErrors } = Component.getComponentHelper();

Component.register('sw-product-packaging-form', {
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
            'purchaseUnit',
            'referenceUnit',
            'packUnit',
            'width',
            'height',
            'length',
            'weight'
        ])
    }
});
