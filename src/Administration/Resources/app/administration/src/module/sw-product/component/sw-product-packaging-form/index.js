import template from './sw-product-packaging-form.html.twig';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors, mapState, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('sw-product-packaging-form', {
    template,

    mixins: [
        Mixin.getByName('placeholder'),
    ],

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },

        showSettingPackaging: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    computed: {
        ...mapGetters('swProductDetail', [
            'isLoading',
        ]),

        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
        ]),

        ...mapPropertyErrors('product', [
            'purchaseUnit',
            'referenceUnit',
            'packUnit',
            'PackUnitPlural',
            'width',
            'height',
            'length',
            'weight',
        ]),
    },
});
