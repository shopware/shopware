import template from './sw-product-feature-set-form.html.twig';
import './sw-product-feature-set-form.scss';

const { Component } = Shopware;
const { mapState } = Shopware.Component.getComponentHelper();

Component.register('sw-product-feature-set-form', {
    template,

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'loading'
        ])
    }
});
