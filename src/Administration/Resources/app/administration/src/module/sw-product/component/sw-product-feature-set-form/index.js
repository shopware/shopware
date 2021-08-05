import template from './sw-product-feature-set-form.html.twig';
import './sw-product-feature-set-form.scss';

const { Component } = Shopware;
const { mapState } = Shopware.Component.getComponentHelper();

Component.register('sw-product-feature-set-form', {
    template,

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'loading',
        ]),
    },
});
