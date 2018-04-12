import { Component, Mixin } from 'src/core/shopware';
import template from './sw-product-detail.html.twig';
import './sw-product-detail.less';

Component.register('sw-product-detail', {
    template,

    mixins: [
        Mixin.getByName('product'),
        Mixin.getByName('manufacturerList'),
        Mixin.getByName('taxList'),
        Mixin.getByName('currencyList'),
        Mixin.getByName('notification')
    ],

    created() {
        if (this.$route.params.id) {
            this.productId = this.$route.params.id;
        }
    },

    methods: {
        onSave() {
            this.saveProduct();
        }
    }
});
