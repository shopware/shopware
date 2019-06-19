import { Component } from 'src/core/shopware';
import { mapState } from 'vuex';
import template from './sw-product-settings-form.html.twig';

Component.register('sw-product-settings-form', {
    template,

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct'
        ])
    }
});
